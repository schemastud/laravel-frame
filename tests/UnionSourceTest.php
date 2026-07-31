<?php

namespace Schemastud\Frame\Tests;

use Schemastud\Frame\Contracts\ResolvedUnionItem;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Contracts\UnionQuery;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\FixtureUnionSource;
use Schemastud\Frame\Tests\Fixtures\ReviewFixtureResourceData;
use Schemastud\Frame\Tests\Fixtures\ReviewItemFixture;

/**
 * Ticket 21 (§2/§3) — the UnionSource contract exercised end to end: a merged stream
 * paginates (perPage slice + advancing cursor), rows carry `source` verbatim, and
 * find(source,id) resolves one item with a nullable schemaRef.
 */
class UnionSourceTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Register a service-kind definition directly through the agnostic port (the
        // producer's discovery is exercised in beam; frame only serves what it is handed).
        $app->singleton(ResourceRegistry::class, function () {
            return (new InMemoryResourceRegistry)->register(new ResourceDefinition(
                key: 'review-fixture',
                sourceKind: 'service',
                model: null,
                source: FixtureUnionSource::class,
                data: ReviewFixtureResourceData::class,
                creatable: false,
                query: null,
                editData: null,
                policy: 'review',
                form: 'bare',
                nav: new NavMetadata(label: 'Review', group: 'Workflow', icon: 'inbox'),
            ));
        });
    }

    public function test_index_paginates_the_merged_stream_and_the_cursor_advances(): void
    {
        $source = new FixtureUnionSource;

        // 8 total items (4 alpha + 4 beta), perPage 3 → page one is a 3-item slice.
        $page1 = $source->index(new UnionQuery(filters: [], cursor: null, perPage: 3));

        $this->assertCount(3, $page1->items());
        $this->assertTrue($page1->hasMorePages());
        $this->assertNotNull($page1->nextCursor());

        // Rows carry `source` verbatim — frame does not interpret it.
        foreach ($page1->items() as $row) {
            $this->assertInstanceOf(ReviewItemFixture::class, $row);
            $this->assertContains($row->source, ['alpha', 'beta']);
        }

        // The cursor advances to a fresh, non-overlapping second page.
        $page2 = $source->index(new UnionQuery(
            filters: [],
            cursor: $page1->nextCursor()->encode(),
            perPage: 3,
        ));

        $page1Ids = collect($page1->items())->pluck('id')->all();
        $page2Ids = collect($page2->items())->pluck('id')->all();

        $this->assertCount(3, $page2->items());
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
    }

    public function test_the_last_page_reports_no_more_pages(): void
    {
        $source = new FixtureUnionSource;

        // perPage 25 easily covers all 8 rows → a single, final page.
        $page = $source->index(new UnionQuery(filters: [], cursor: null, perPage: 25));

        $this->assertCount(8, $page->items());
        $this->assertFalse($page->hasMorePages());
    }

    public function test_the_source_facet_filters_the_stream(): void
    {
        $source = new FixtureUnionSource;

        $page = $source->index(new UnionQuery(filters: ['source' => 'alpha'], perPage: 25));

        $this->assertCount(4, $page->items());
        foreach ($page->items() as $row) {
            $this->assertSame('alpha', $row->source);
        }
    }

    public function test_find_returns_the_alpha_item_with_a_null_schema_ref(): void
    {
        $resolved = (new FixtureUnionSource)->find('alpha', 'a1');

        $this->assertInstanceOf(ResolvedUnionItem::class, $resolved);
        $this->assertSame('alpha', $resolved->item->source);
        $this->assertSame('a1', $resolved->item->id);
        $this->assertNull($resolved->schemaRef);
    }

    public function test_find_returns_the_beta_item_with_a_non_null_schema_ref(): void
    {
        $resolved = (new FixtureUnionSource)->find('beta', 'b2');

        $this->assertInstanceOf(ResolvedUnionItem::class, $resolved);
        $this->assertSame('beta', $resolved->item->source);
        $this->assertSame('swc://fixture/beta/2', $resolved->schemaRef);
    }

    public function test_find_of_a_missing_id_returns_null(): void
    {
        $this->assertNull((new FixtureUnionSource)->find('alpha', 'nope'));
    }

    public function test_the_manifest_emits_the_service_resource_as_a_capability_contract(): void
    {
        $response = $this->getJson('/frame/manifest');

        $response->assertOk();
        $response->assertJsonPath('resources.0.key', 'review-fixture');
        $response->assertJsonPath('resources.0.sourceKind', 'service');
        $response->assertJsonPath('resources.0.creatable', false);
        $response->assertJsonPath('resources.0.model', null);
        $response->assertJsonPath('resources.0.source', FixtureUnionSource::class);
        $response->assertJsonPath('resources.0.editData', null);
        $response->assertJsonPath('resources.0.query', null);
    }
}
