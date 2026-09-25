<?php

namespace Schemastud\Frame\Tests;

use Illuminate\Database\Eloquent\Model;
use Mockery;
use Schemastud\Frame\Contracts\FrameResourceHandlerResolver;
use Schemastud\Frame\Contracts\ResourceAccessGate;
use Schemastud\Frame\Contracts\ResourceActionAuthorizer;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceActionDefinition;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\SampleActionInputData;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

/**
 * ADR-0005 — a framed resource's ACTIONS: the generic slot on {@see ResourceDefinition}, their projection
 * onto the manifest's per-resource context block, the per-actor `can.actions` map asked of the producer's
 * {@see ResourceActionAuthorizer}, and the action form's schema endpoint.
 */
class ResourceActionsTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton(ResourceRegistry::class, fn () => (new InMemoryResourceRegistry)
            ->register($this->definition('plain', []))
            ->register($this->definition('wallets', [
                new ResourceActionDefinition(
                    key: 'reload',
                    label: 'Reload credits',
                    scope: ResourceActionDefinition::ScopeResource,
                    method: 'POST',
                    url: '/api/wallets/reload',
                    input: SampleActionInputData::class,
                ),
                new ResourceActionDefinition(
                    key: 'archive',
                    label: 'Archive',
                    scope: ResourceActionDefinition::ScopeRecord,
                    method: 'POST',
                    url: '/api/wallets/{id}/archive',
                    result: ResourceActionDefinition::ResultNavigate,
                    destructive: true,
                ),
            ])));

        // The schema endpoint never reaches a handler; the controller only needs one to construct.
        $app->instance(FrameResourceHandlerResolver::class, Mockery::mock(FrameResourceHandlerResolver::class));
    }

    /** @param list<ResourceActionDefinition> $actions */
    private function definition(string $key, array $actions): ResourceDefinition
    {
        return new ResourceDefinition(
            key: $key,
            model: SampleModel::class,
            data: SampleResourceData::class,
            creatable: false,
            query: null,
            editData: null,
            policy: null,
            form: 'bare',
            nav: new NavMetadata(label: ucfirst($key)),
            actions: $actions,
        );
    }

    public function test_an_action_serializes_with_its_input_as_the_generated_type_name_and_no_class_string(): void
    {
        $wire = $this->app->make(ResourceRegistry::class)->get('wallets')->toArray();

        $this->assertSame([
            'key' => 'reload',
            'label' => 'Reload credits',
            'scope' => 'resource',
            'method' => 'POST',
            'url' => '/api/wallets/reload',
            'input' => 'Schemastud.Frame.Tests.Fixtures.SampleActionInputData',
            'result' => 'toast',
            'destructive' => false,
        ], $wire['actions'][0]);

        // A confirm-only action carries no input at all.
        $this->assertNull($wire['actions'][1]['input']);
        $this->assertStringNotContainsString('\\', json_encode($wire['actions'], JSON_UNESCAPED_SLASHES));
    }

    public function test_with_overrides_keeps_the_actions_unless_replaced(): void
    {
        $definition = $this->app->make(ResourceRegistry::class)->get('wallets');

        $this->assertCount(2, $definition->withOverrides(label: 'Wallets')->actions);
        $this->assertSame([], $definition->withOverrides(actions: [])->actions);
        $this->assertSame('archive', $definition->action('archive')?->key);
        $this->assertNull($definition->action('missing'));
    }

    public function test_a_resource_without_actions_keeps_its_exact_manifest_block(): void
    {
        $plain = $this->getJson('/frame/manifest')->assertOk()->json('contexts.plain');

        $this->assertArrayNotHasKey('actions', $plain);
        $this->assertArrayNotHasKey('actions', $plain['can']);
    }

    public function test_the_manifest_carries_the_actions_and_denies_them_all_when_no_producer_answers(): void
    {
        $block = $this->getJson('/frame/manifest')->assertOk()->json('contexts.wallets');

        $this->assertSame(['reload', 'archive'], array_column($block['actions'], 'key'));
        $this->assertSame('Schemastud.Frame.Tests.Fixtures.SampleActionInputData', $block['actions'][0]['input']);
        // Frame's own default port answers no: frame cannot know the rule behind a producer's URL.
        $this->assertSame(['reload' => false, 'archive' => false], $block['can']['actions']);
    }

    public function test_the_per_actor_map_is_the_producers_answer_per_action(): void
    {
        $this->app->instance(ResourceActionAuthorizer::class, new class implements ResourceActionAuthorizer
        {
            public function allows(ResourceDefinition $definition, ResourceActionDefinition $action, ?Model $record = null): bool
            {
                return $action->key === 'reload' && $record === null;
            }
        });

        $block = $this->getJson('/frame/manifest')->assertOk()->json('contexts.wallets');

        $this->assertSame(['reload' => true, 'archive' => false], $block['can']['actions']);
    }

    public function test_an_authorizer_that_refuses_by_throwing_reads_as_false_not_a_broken_manifest(): void
    {
        $this->app->instance(ResourceActionAuthorizer::class, new class implements ResourceActionAuthorizer
        {
            public function allows(ResourceDefinition $definition, ResourceActionDefinition $action, ?Model $record = null): bool
            {
                abort(403);
            }
        });

        $this->getJson('/frame/manifest')->assertOk()
            ->assertJsonPath('contexts.wallets.can.actions.reload', false);
    }

    public function test_the_action_schema_endpoint_reflects_the_input_in_request_mode(): void
    {
        $schema = $this->getJson('/frame/resources/wallets/actions/reload/schema')->assertOk()->json();

        // Request mode names the key the action's URL accepts, not the PHP property.
        $this->assertArrayHasKey('amount_usd', $schema['properties']);
        $this->assertContains('amount_usd', $schema['required']);
    }

    public function test_a_confirm_only_or_unknown_action_has_no_form_to_serve(): void
    {
        $this->getJson('/frame/resources/wallets/actions/archive/schema')->assertNotFound();
        $this->getJson('/frame/resources/wallets/actions/nope/schema')->assertNotFound();
        $this->getJson('/frame/resources/nope/actions/reload/schema')->assertNotFound();
    }

    public function test_the_action_schema_is_behind_the_resource_access_gate(): void
    {
        $this->app->bind(ResourceAccessGate::class, fn () => new class implements ResourceAccessGate
        {
            public function allowsResource(ResourceDefinition $definition): bool
            {
                return false;
            }
        });

        $this->getJson('/frame/resources/wallets/actions/reload/schema')->assertForbidden();
    }
}
