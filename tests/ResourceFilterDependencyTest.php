<?php

namespace Schemastud\Frame\Tests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Schemastud\Frame\Authorization\ResourceAuthorizer;
use Schemastud\Frame\Contracts\ResourceAccessGate;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

class ResourceFilterDependencyTest extends TestCase
{
    private function definition(string $key, ?string $provider = null): ResourceDefinition
    {
        return new ResourceDefinition(
            key: $key, model: SampleModel::class, data: SampleResourceData::class,
            creatable: true, query: null, editData: null, policy: null,
            form: 'bare', nav: new NavMetadata(label: $key), filterProvider: $provider,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs((new User)->forceFill(['id' => 7]));
        Gate::policy(SampleModel::class, FilterDependencyPolicy::class);
        $this->app->instance(ResourceRegistry::class, (new InMemoryResourceRegistry)
            ->register($this->definition('articles', ArticleFilters::class)));
    }

    public function test_missing_or_denied_saved_resource_does_not_hide_usable_filters(): void
    {
        $this->app->instance(ResourceAccessGate::class, new class implements ResourceAccessGate
        {
            public function allowsResource(ResourceDefinition $definition): bool
            {
                return $definition->key !== 'named-views';
            }
        });
        $this->getJson('/frame/resources/articles/filters/schema')->assertOk()
            ->assertJsonPath('data.properties.title.x-filter.name', 'title')
            ->assertJsonPath('savedViewsResource', null)->assertJsonPath('savedViewsCan', null);
        app(ResourceRegistry::class)->register($this->definition('named-views'));
        $this->getJson('/frame/resources/articles/filters/schema')->assertOk()
            ->assertJsonPath('savedViewsResource', null)->assertJsonPath('savedViewsCan', null);
    }

    public function test_readable_saved_resource_reports_create_denial_without_resolving_its_provider(): void
    {
        app(ResourceRegistry::class)->register($this->definition('named-views', ArticleFilters::class));
        $calls = 0;
        $this->app->bind(ArticleFilters::class, function () use (&$calls) {
            $calls++;

            return new ArticleFilters;
        });
        $this->getJson('/frame/resources/articles/filters/schema')->assertOk()
            ->assertJsonPath('savedViewsResource', 'named-views')
            ->assertJsonPath('savedViewsCan.create', false);
        $this->assertSame(1, $calls);
        // The saved resource points to itself; dependency discovery must not recurse.
        $this->getJson('/frame/resources/named-views/filters/schema')->assertOk();
        $this->assertSame(2, $calls);
    }

    public function test_record_capabilities_use_the_actual_row_and_preserve_policy_denial_status(): void
    {
        $owner = new SampleModel(['title' => 'owned']);
        $other = new SampleModel(['title' => 'shared']);
        $definition = $this->definition('named-views');
        $authorizer = app(ResourceAuthorizer::class);
        $this->assertSame(['create' => false, 'update' => true, 'delete' => true], $authorizer->recordCapabilities($definition, $owner));
        $this->assertSame(['create' => false, 'update' => false, 'delete' => false], $authorizer->recordCapabilities($definition, $other));
        $this->assertFalse($authorizer->recordCapabilities($definition->withOverrides(deletable: false), $owner)['delete']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        Gate::authorize('delete', $other);
    }

    public function test_record_capabilities_refuse_unreachable_resources_and_wrong_model_subjects(): void
    {
        $this->assertSame(['create' => false, 'update' => false, 'delete' => false],
            app(ResourceAuthorizer::class)->recordCapabilities($this->definition('named-views'), new User));
        $this->app->instance(ResourceAccessGate::class, new class implements ResourceAccessGate
        {
            public function allowsResource(ResourceDefinition $definition): bool
            {
                return false;
            }
        });
        $this->assertSame(['create' => false, 'update' => false, 'delete' => false],
            app(ResourceAuthorizer::class)->recordCapabilities($this->definition('named-views'), new SampleModel(['title' => 'owned'])));
    }

    public function test_advisory_does_not_hide_a_policy_programming_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Policy bug');
        app(ResourceAuthorizer::class)->recordCapabilities($this->definition('named-views'), new SampleModel(['title' => 'broken']));
    }

    public function test_advisory_does_not_hide_a_policy_service_failure(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException::class);
        app(ResourceAuthorizer::class)->recordCapabilities($this->definition('named-views'), new SampleModel(['title' => 'unavailable']));
    }
}

class FilterDependencyPolicy
{
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SampleModel $record): bool
    {
        if ($record->title === 'broken') {
            throw new \RuntimeException('Policy bug');
        }
        if ($record->title === 'unavailable') {
            throw new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
        }
        abort_unless($record->title === 'owned', 404);

        return true;
    }

    public function delete(User $user, SampleModel $record): bool
    {
        return $this->update($user, $record);
    }
}
