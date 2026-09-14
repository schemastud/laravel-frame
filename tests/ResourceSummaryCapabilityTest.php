<?php

namespace Schemastud\Frame\Tests;

use LogicException;
use Schemastud\Frame\Contracts\ResourceAccessGate;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Contracts\ResourceSummaryProvider;
use Schemastud\Frame\Data\SummaryFigureData;
use Schemastud\Frame\Data\SummaryResponseData;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

/**
 * `GET resources/{resource}/summary` — the summary capability, resolved through the resource's declared
 * provider slot exactly as the filter capability is ({@see ResourceFilterCapabilityTest} is the template).
 */
class ResourceSummaryCapabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(ResourceRegistry::class, (new InMemoryResourceRegistry)
            ->register($this->resource('articles', ArticleSummary::class))
            ->register($this->resource('closed', ArticleSummary::class))
            ->register($this->resource('declining', DecliningSummary::class))
            ->register($this->resource('mistyped', NotASummaryProvider::class))
            ->register($this->resource('plain')));
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
            form: 'bare', nav: new NavMetadata(label: ucfirst($key), icon: 'newspaper'), summaryProvider: $provider,
        );
    }

    public function test_a_resource_with_a_provider_answers_its_figures(): void
    {
        $this->getJson('/frame/resources/articles/summary')->assertOk()->assertExactJson([
            'key' => 'articles',
            'label' => 'Articles',
            'icon' => 'newspaper',
            'figures' => [
                ['key' => 'total', 'label' => 'Articles', 'value' => 42, 'tone' => null],
                ['key' => 'drafts', 'label' => 'Drafts', 'value' => 3, 'tone' => 'warning'],
            ],
            'overview' => null,
        ]);
    }

    public function test_no_provider_and_a_declining_provider_both_answer_not_found(): void
    {
        $this->getJson('/frame/resources/plain/summary')->assertNotFound();
        $this->getJson('/frame/resources/declining/summary')->assertNotFound();
        $this->getJson('/frame/resources/unknown/summary')->assertNotFound();
    }

    public function test_unknown_and_denied_resources_never_resolve_a_provider(): void
    {
        $calls = 0;
        $this->app->bind(ArticleSummary::class, function () use (&$calls) {
            $calls++;

            return new ArticleSummary;
        });

        $this->getJson('/frame/resources/closed/summary')->assertForbidden();
        $this->getJson('/frame/resources/unknown/summary')->assertNotFound();
        $this->assertSame(0, $calls);
        $this->getJson('/frame/resources/articles/summary')->assertOk();
        $this->assertSame(1, $calls, 'The permitted control must actually resolve the declared provider.');
    }

    public function test_a_provider_that_does_not_implement_the_contract_is_a_logic_error(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Summary provider for 'mistyped' must implement ResourceSummaryProvider.");

        $this->getJson('/frame/resources/mistyped/summary');
    }

    public function test_definition_overrides_preserve_provider_without_exposing_its_class(): void
    {
        $definition = $this->resource('articles', ArticleSummary::class)->withOverrides(label: 'Posts');
        $this->app->instance(ResourceRegistry::class, (new InMemoryResourceRegistry)->register($definition));
        $this->getJson('/frame/resources/articles/summary')->assertOk()->assertJsonPath('label', 'Posts');
        $this->assertArrayNotHasKey('summaryProvider', $definition->toArray());
        $this->assertStringNotContainsString(ArticleSummary::class, $definition->toJson());
        $this->assertSame(DecliningSummary::class, $definition->withOverrides(summaryProvider: DecliningSummary::class)->summaryProvider);
    }

    public function test_a_host_can_mount_the_same_interface_with_its_own_prefix_and_context(): void
    {
        \Illuminate\Support\Facades\Route::prefix('editor')->group(function () {
            \Schemastud\Frame\Routing\ResourceRoutes::summary(
                at: 'catalog/{resource}', names: 'editor.catalog', defaults: ['realm' => 'operator'],
            );
        });
        $this->getJson('/editor/catalog/articles/summary')->assertOk()->assertJsonPath('figures.0.value', 42);
        $route = $this->app['router']->getRoutes()->getByName('editor.catalog.summary');
        $this->assertSame('operator', $route->defaults['realm']);
    }
}

class ArticleSummary implements ResourceSummaryProvider
{
    public function summary(ResourceDefinition $resource): ?SummaryResponseData
    {
        return new SummaryResponseData(
            key: $resource->key,
            label: $resource->nav->label,
            icon: $resource->nav->icon,
            figures: [
                new SummaryFigureData('total', $resource->nav->label, 42),
                new SummaryFigureData('drafts', 'Drafts', 3, 'warning'),
            ],
        );
    }
}

class DecliningSummary implements ResourceSummaryProvider
{
    public function summary(ResourceDefinition $resource): ?SummaryResponseData
    {
        return null;
    }
}

class NotASummaryProvider
{
    public function summary(ResourceDefinition $resource): ?SummaryResponseData
    {
        return null;
    }
}
