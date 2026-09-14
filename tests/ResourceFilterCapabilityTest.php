<?php

namespace Schemastud\Frame\Tests;

use Schemastud\Frame\Contracts\ResourceAccessGate;
use Schemastud\Frame\Contracts\ResourceFilterProvider;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Data\FilterOptionData;
use Schemastud\Frame\Data\FilterOptionsResponseData;
use Schemastud\Frame\Data\FilterSchemaResponseData;
use Schemastud\Frame\Data\FilterVariantData;
use Schemastud\Frame\Data\FilterVariantsData;
use Schemastud\Frame\Data\FilterVariantsResponseData;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

class ResourceFilterCapabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(ResourceRegistry::class, (new InMemoryResourceRegistry)
            ->register($this->resource('articles', ArticleFilters::class))
            ->register($this->resource('people', PeopleFilters::class))
            ->register($this->resource('closed', ArticleFilters::class))
            ->register($this->resource('plain'))
            ->register($this->resource('named-views')));
        $this->app->instance(ResourceAccessGate::class, new class implements ResourceAccessGate
        {
            public function allowsResource(ResourceDefinition $definition): bool
            {
                return $definition->key !== 'closed';
            }
        });
    }

    private function resource(string $key, ?string $provider = null): ResourceDefinition
    {
        return new ResourceDefinition(
            key: $key, model: null, data: SampleResourceData::class,
            creatable: false, query: null, editData: null, policy: null,
            form: 'bare', nav: new NavMetadata(label: $key), filterProvider: $provider,
        );
    }

    public function test_each_registered_resource_serves_its_declared_filter_capability(): void
    {
        $this->getJson('/frame/resources/articles/filters/schema')->assertOk()
            ->assertJsonPath('data.properties.title.x-filter.name', 'title')
            ->assertJsonPath('savedViewsResource', 'named-views');
        $this->getJson('/frame/resources/people/filters/schema')->assertOk()
            ->assertJsonPath('data.properties.email.x-filter.name', 'email')
            ->assertJsonPath('savedViewsResource', null);
    }

    public function test_unknown_and_denied_resources_never_resolve_a_provider(): void
    {
        $calls = 0;
        $this->app->bind(ArticleFilters::class, function () use (&$calls) {
            $calls++;

            return new ArticleFilters;
        });

        foreach (['schema', 'options/authors?search[]=bad', 'variants', 'compact/schema'] as $suffix) {
            $this->getJson('/frame/resources/closed/filters/'.$suffix)->assertForbidden();
            $this->getJson('/frame/resources/unknown/filters/'.$suffix)->assertNotFound();
        }
        $this->assertSame(0, $calls);
        $this->getJson('/frame/resources/articles/filters/schema')->assertOk();
        $this->assertSame(1, $calls, 'The permitted control must actually resolve the declared provider.');
    }

    public function test_options_preserve_resource_reference_and_search(): void
    {
        $this->getJson('/frame/resources/articles/filters/options/authors?search=Ada%20Lovelace')
            ->assertOk()->assertExactJson(['data' => [['value' => 'articles', 'label' => 'Ada Lovelace']]]);
        $this->getJson('/frame/resources/articles/filters/options/unrelated')->assertNotFound();
        $this->getJson('/frame/resources/articles/filters/options/authors?search[]=bad')
            ->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public function test_variants_are_resource_scoped_and_unknown_variants_fail(): void
    {
        $this->getJson('/frame/resources/articles/filters/variants')->assertOk()->assertExactJson([
            'data' => ['resource' => 'articles', 'variants' => [[
                'key' => 'compact', 'resource' => 'articles', 'canonical' => false, 'sameAsCanonical' => false,
            ]]],
        ]);
        $this->getJson('/frame/resources/articles/filters/compact/schema')->assertOk();
        $this->getJson('/frame/resources/articles/filters/unrelated/schema')->assertNotFound();
    }

    public function test_no_provider_means_no_saved_views_and_an_object_shaped_empty_vocabulary(): void
    {
        $response = $this->getJson('/frame/resources/plain/filters/schema')->assertOk()
            ->assertJsonPath('savedViewsResource', null);
        $this->assertInstanceOf(\stdClass::class, json_decode($response->getContent())->data->properties);
        $this->getJson('/frame/resources/plain/filters/options/authors')->assertNotFound();
        $this->getJson('/frame/resources/plain/filters/compact/schema')->assertNotFound();
        $this->getJson('/frame/resources/plain/filters/variants')->assertOk()->assertExactJson([
            'data' => ['resource' => 'plain', 'variants' => []],
        ]);
    }

    public function test_definition_overrides_preserve_provider_without_exposing_its_class(): void
    {
        $definition = $this->resource('articles', ArticleFilters::class)->withOverrides(label: 'Posts');
        $this->app->instance(ResourceRegistry::class, (new InMemoryResourceRegistry)->register($definition));
        $this->getJson('/frame/resources/articles/filters/schema')->assertOk()
            ->assertJsonPath('data.properties.title.x-filter.name', 'title');
        $this->assertArrayNotHasKey('filterProvider', $definition->toArray());
        $this->assertStringNotContainsString(ArticleFilters::class, $definition->toJson());
    }

    public function test_flat_filter_routes_are_not_part_of_the_frame_surface(): void
    {
        // The HTTP misses plus the route table distinguish removal from a handler-level 404.
        foreach (['filter-schema/articles', 'filter-options/authors', 'saved-filters'] as $path) {
            $this->getJson('/frame/'.$path)->assertNotFound();
        }
        $this->postJson('/frame/saved-filters', ['resource' => 'articles', 'name' => 'View'])->assertNotFound();
        $this->deleteJson('/frame/saved-filters/123')->assertNotFound();
        $this->assertFalse($this->app['router']->has('frame.saved-filters'));
        $this->assertFalse($this->app['router']->has('frame.filter-options'));
    }

    public function test_a_host_can_mount_the_same_interface_with_its_own_prefix_and_context(): void
    {
        \Illuminate\Support\Facades\Route::prefix('editor')->group(function () {
            \Schemastud\Frame\Routing\ResourceRoutes::filters(
                at: 'catalog/{resource}', names: 'editor.catalog', defaults: ['realm' => 'operator'],
            );
        });
        $this->getJson('/editor/catalog/articles/filters/options/authors?search=Grace')->assertOk()
            ->assertExactJson(['data' => [['value' => 'articles', 'label' => 'Grace']]]);
        $route = $this->app['router']->getRoutes()->getByName('editor.catalog.filters.options');
        $this->assertSame('operator', $route->defaults['realm']);
    }
}

class ArticleFilters implements ResourceFilterProvider
{
    public function schema(ResourceDefinition $resource, ?string $variant = null): FilterSchemaResponseData
    {
        abort_if($variant !== null && $variant !== 'compact', 404);

        return new FilterSchemaResponseData(['properties' => [
            'title' => ['x-filter' => ['name' => 'title', 'control' => 'text', 'operator' => 'partial']],
        ]], 'named-views');
    }

    public function options(ResourceDefinition $resource, string $ref, ?string $search = null): FilterOptionsResponseData
    {
        abort_unless($ref === 'authors', 404);

        return new FilterOptionsResponseData([new FilterOptionData($resource->key, $search ?? 'All authors')]);
    }

    public function variants(ResourceDefinition $resource): FilterVariantsResponseData
    {
        return new FilterVariantsResponseData(new FilterVariantsData($resource->key, [
            new FilterVariantData('compact', $resource->key, false, false),
        ]));
    }
}

class PeopleFilters extends ArticleFilters
{
    public function schema(ResourceDefinition $resource, ?string $variant = null): FilterSchemaResponseData
    {
        return new FilterSchemaResponseData(['properties' => [
            'email' => ['x-filter' => ['name' => 'email', 'control' => 'text', 'operator' => 'partial']],
        ]]);
    }
}
