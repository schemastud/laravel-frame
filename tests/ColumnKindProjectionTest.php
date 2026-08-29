<?php

namespace Schemastud\Frame\Tests;

use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Tests\Fixtures\ColumnKindResourceData;

/**
 * `#[Column]`'s presentation channel — the kind and its options, on the wire.
 *
 * The column SET has been declaration-driven for a while; how a cell RENDERS was not, and
 * nine frame lists at the flagship carried 51 hand-written `cell` closures because of it.
 * The channel needed for that was almost entirely already here: `$widget` has always
 * projected through to `byNode[field]['list-column'].widget` and no client read it, so
 * `#[Column('badge')]` was a comment. The only genuinely missing piece was a way to
 * CONFIGURE the kind, which is what `$options` adds.
 *
 * So these tests are mostly about what did NOT change: the wire shape for a plain
 * `#[Column]`, and the fact that `filterable` still lands where it always did.
 */
class ColumnKindProjectionTest extends TestCase
{
    private function node(string $field): array
    {
        return (new ContextManifest)->forResource(ColumnKindResourceData::class)['byNode'][$field]['list-column'];
    }

    public function test_the_kind_rides_the_widget_slot_that_already_existed(): void
    {
        $this->assertSame('badge', $this->node('kind')['widget']);
        $this->assertSame('date', $this->node('declaredAt')['widget']);
    }

    public function test_options_reach_the_wire_verbatim(): void
    {
        $this->assertSame(['variant' => 'secondary'], $this->node('kind')['options']);
    }

    public function test_a_kind_with_no_options_emits_no_options_key_at_all(): void
    {
        // The projector's strict no-op: an absent bag is absent, not an empty array. A
        // client reading `options ?? {}` cannot tell the two apart, but the wire contract
        // can, and every other entry field in this projection behaves this way.
        $this->assertArrayNotHasKey('options', $this->node('declaredAt'));
    }

    public function test_filterable_and_kind_options_share_the_bag_without_either_erasing_the_other(): void
    {
        $this->assertSame(
            ['filterable' => true, 'zeroAsDash' => true],
            $this->node('count')['options'],
        );
    }

    public function test_a_column_declaring_no_kind_is_byte_identical_to_before(): void
    {
        $this->assertSame(
            ['participates' => true, 'sort' => 3, 'label' => 'Plain'],
            $this->node('plain'),
        );
    }

    public function test_label_and_sort_still_ride_alongside_the_kind(): void
    {
        $kind = $this->node('kind');

        $this->assertTrue($kind['participates']);
        $this->assertSame('Kind', $kind['label']);
        $this->assertSame(0, $kind['sort']);
    }
}
