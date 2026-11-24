<?php

namespace Schemastud\Frame\Tests;

use Mockery;
use Schemastud\Frame\Contracts\FrameResourceHandlerResolver;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceActionDefinition;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\ContactResourceData;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Spatie\LaravelData\Optional;

/**
 * frame-foundation 83: frame's manifest carries each resource's agreement `requires` (agreements 05's shape, beam
 * ADR-0247 amendment): keys only, keyed `{key}:read` / `{key}:write` on the definition and copied into its context
 * block, and on each action by its op's dotted key. OMITTED when there are none, so a host with no Requirement serves a
 * byte-identical manifest.
 */
class RequiresManifestTest extends TestCase
{
    public function test_a_resource_with_requires_carries_them_on_the_entry_the_context_block_and_its_actions(): void
    {
        $definition = $this->definition(
            requires: ResourceDefinition::requiresFor(['notes:read' => ['terms'], 'notes:write' => ['terms', 'privacy']]),
            actions: [$this->action('publish', ['terms']), $this->action('archive')],
        );

        $manifest = $this->manifest($definition);

        $this->assertSame(['notes:read' => ['terms'], 'notes:write' => ['terms', 'privacy']], $manifest['resources'][0]['requires']);
        $this->assertSame(['notes:read' => ['terms'], 'notes:write' => ['terms', 'privacy']], $manifest['contexts']['notes']['requires']);
        $this->assertSame(['terms'], $manifest['contexts']['notes']['actions'][0]['requires']);
        $this->assertArrayNotHasKey('requires', $manifest['contexts']['notes']['actions'][1], 'an action with none emits no key');
        $this->assertSame(['terms'], $manifest['resources'][0]['actions'][0]['requires']);
        $this->assertStringNotContainsString('\\\\', json_encode($manifest['contexts']['notes']['requires']), 'keys only, no class names');
    }

    public function test_a_resource_with_none_emits_no_requires_key_and_a_byte_identical_manifest(): void
    {
        $plain = $this->manifest($this->definition(actions: [$this->action('archive')]));
        $emptied = $this->manifest($this->definition(requires: ResourceDefinition::requiresFor(['notes:read' => [], 'notes:write' => []]), actions: [$this->action('archive')]));

        $this->assertArrayNotHasKey('requires', $plain['resources'][0]);
        $this->assertArrayNotHasKey('requires', $plain['contexts']['notes']);
        $this->assertArrayNotHasKey('requires', $plain['contexts']['notes']['actions'][0]);
        $this->assertSame(json_encode($plain), json_encode($emptied), 'an all-empty map is the same as none');
    }

    /** review LOW 2: the constructors hold the invariant for ANY producer, not only requiresFor()'s callers. */
    public function test_empty_lists_and_maps_passed_straight_to_the_constructors_never_reach_the_wire(): void
    {
        foreach ([[], ['notes:read' => []], ['notes:read' => [], 'notes:write' => []]] as $requires) {
            $manifest = $this->manifest($this->definition(requires: $requires, actions: [$this->action('archive', [])]));

            $this->assertArrayNotHasKey('requires', $manifest['resources'][0], json_encode($requires));
            $this->assertArrayNotHasKey('requires', $manifest['contexts']['notes'], json_encode($requires));
            $this->assertArrayNotHasKey('requires', $manifest['contexts']['notes']['actions'][0], json_encode($requires));
            $this->assertArrayNotHasKey('requires', $manifest['resources'][0]['actions'][0], json_encode($requires));
        }

        $partial = $this->manifest($this->definition(requires: ['notes:read' => [], 'notes:write' => ['terms', 'terms']]));
        $this->assertSame(['notes:write' => ['terms']], $partial['resources'][0]['requires']);
        $this->assertSame(['notes:write' => ['terms']], $partial['contexts']['notes']['requires']);
    }

    public function test_requires_for_drops_empty_and_duplicate_keys_and_is_optional_when_nothing_is_left(): void
    {
        $this->assertSame(['a:write' => ['terms']], ResourceDefinition::requiresFor(['a:read' => [], 'a:write' => ['terms', 'terms', '']]));
        $this->assertInstanceOf(Optional::class, ResourceDefinition::requiresFor([]));
        $this->assertInstanceOf(Optional::class, $this->definition()->requires, 'the slot defaults to none');
    }

    /** @param  list<ResourceActionDefinition>  $actions */
    private function definition(array|Optional $requires = new Optional, array $actions = []): ResourceDefinition
    {
        return new ResourceDefinition(
            key: 'notes',
            model: SampleModel::class,
            data: ContactResourceData::class,
            creatable: true,
            query: null,
            editData: null,
            policy: null,
            nav: new NavMetadata(label: 'Notes'),
            actions: $actions,
            requires: $requires,
        );
    }

    /** @param  list<string>|null  $requires */
    private function action(string $key, ?array $requires = null): ResourceActionDefinition
    {
        return new ResourceActionDefinition(
            key: $key, label: ucfirst($key), scope: \Schemastud\Frame\Registry\ResourceActionScope::Record, method: 'POST', url: "/api/notes/{id}/{$key}",
            requires: $requires ?? new Optional,
        );
    }

    /** @return array<string, mixed> */
    private function manifest(ResourceDefinition $definition): array
    {
        $registry = new InMemoryResourceRegistry;
        $registry->register($definition);
        $this->app->instance(ResourceRegistry::class, $registry);
        $this->app->instance(FrameResourceHandlerResolver::class, Mockery::mock(FrameResourceHandlerResolver::class));

        return $this->getJson('frame/manifest')->assertOk()->json();
    }
}
