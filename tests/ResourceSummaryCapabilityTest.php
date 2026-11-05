<?php

namespace Schemastud\Frame\Tests;

use LogicException;
use Schemastud\Frame\Contracts\ResourceAccessGate;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Contracts\ResourceSummaryProvider;
use Schemastud\Frame\Data\OverviewData;
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
            ->register($this->resource('expanded', ExpandedSummary::class))
            ->register($this->resource('trend', ForecastSummary::class))
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

    /**
     * The other half of the `overview` slot: a provider that DOES expand passes a declared
     * {@see \Schemastud\Frame\Data\OverviewData} through to the wire whole. The null case above proves the
     * slot is optional; only this one proves it carries anything, and that it is a shape rather than the
     * `mixed` bag it started as.
     */
    public function test_a_provider_that_expands_puts_a_declared_overview_on_the_wire(): void
    {
        $this->getJson('/frame/resources/expanded/summary')->assertOk()->assertExactJson([
            'key' => 'expanded',
            'label' => 'Expanded',
            'icon' => 'newspaper',
            'figures' => [['key' => 'total', 'label' => 'Expanded', 'value' => 9, 'tone' => null]],
            'overview' => [
                'headline' => ['key' => 'revenue', 'label' => 'Revenue', 'value' => '$1,240', 'tone' => 'positive'],
                'items' => [['id' => 1, 'title' => 'First'], ['id' => 2, 'title' => 'Second']],
                'period' => 'Last 30 days',
                'note' => 'Excludes drafts.',
            ],
        ]);
    }

    /**
     * `forecast` is on the wire only when the producer declares it. A figure that never mentions it keeps the
     * exact bytes every summary had before the field existed (the two tests above pin that), and true and false
     * both reach the client as declared, for a series widget to tell a projected period from a measured one.
     */
    public function test_a_figure_carries_forecast_only_when_its_producer_declares_it(): void
    {
        $this->getJson('/frame/resources/trend/summary')->assertOk()->assertExactJson([
            'key' => 'trend',
            'label' => 'Trend',
            'icon' => 'newspaper',
            'figures' => [
                ['key' => '2026-09', 'label' => 'Sep 2026', 'value' => 100, 'tone' => null],
                ['key' => '2026-10', 'label' => 'Oct 2026', 'value' => 110, 'tone' => null, 'forecast' => false],
                ['key' => '2026-11', 'label' => 'Nov 2026', 'value' => 121, 'tone' => null, 'forecast' => true],
                ['key' => 'note', 'label' => 'Note', 'value' => 'n/a', 'tone' => null, 'forecast' => null],
            ],
            'overview' => null,
        ]);

        $this->assertArrayNotHasKey('forecast', (new SummaryFigureData('total', 'Total', 1))->toArray());
        $this->assertTrue(SummaryFigureData::from(['key' => 'k', 'label' => 'L', 'value' => 1, 'forecast' => true])->forecast);
        $this->assertArrayNotHasKey('forecast', SummaryFigureData::from(['key' => 'k', 'label' => 'L', 'value' => 1])->toArray());
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

class ForecastSummary implements ResourceSummaryProvider
{
    public function summary(ResourceDefinition $resource): ?SummaryResponseData
    {
        return new SummaryResponseData(
            key: $resource->key,
            label: $resource->nav->label,
            icon: $resource->nav->icon,
            figures: [
                new SummaryFigureData('2026-09', 'Sep 2026', 100),
                new SummaryFigureData('2026-10', 'Oct 2026', 110, forecast: false),
                new SummaryFigureData('2026-11', 'Nov 2026', 121, forecast: true),
                new SummaryFigureData('note', 'Note', 'n/a', forecast: null),
            ],
        );
    }
}

class ExpandedSummary implements ResourceSummaryProvider
{
    public function summary(ResourceDefinition $resource): ?SummaryResponseData
    {
        return new SummaryResponseData(
            key: $resource->key,
            label: $resource->nav->label,
            icon: $resource->nav->icon,
            figures: [new SummaryFigureData('total', $resource->nav->label, 9)],
            overview: new OverviewData(
                headline: new SummaryFigureData('revenue', 'Revenue', '$1,240', 'positive'),
                items: [['id' => 1, 'title' => 'First'], ['id' => 2, 'title' => 'Second']],
                period: 'Last 30 days',
                note: 'Excludes drafts.',
            ),
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
