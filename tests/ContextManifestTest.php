<?php

namespace Schemastud\Frame\Tests;

use InvalidArgumentException;
use Schemastud\Frame\Attributes\Overview;
use Schemastud\Frame\Attributes\Summary;
use Schemastud\Frame\Attributes\WidgetIn;
use Schemastud\Frame\Contracts\ResourceContextContributor;
use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Tests\Fixtures\BadClassContextData;
use Schemastud\Frame\Tests\Fixtures\ContactResourceData;
use Schemastud\Frame\Tests\Fixtures\RowActionsResourceData;
use Schemastud\Frame\Tests\Fixtures\SummaryOverviewResourceData;

/**
 * The {byNode, inherits, known} render-context block for one resource's Data class —
 * root ("") carries the class-level list-item/summary/overview/row-actions entries,
 * each property carries its per-context map. Shares projection + validation with the
 * strategy.
 */
class ContextManifestTest extends TestCase
{
    private function block(): array
    {
        return (new ContextManifest)->forResource(ContactResourceData::class);
    }

    public function test_known_is_the_closed_seven_context_enum(): void
    {
        $this->assertSame(
            ['edit', 'detail', 'list-column', 'list-item', 'row-cell', 'summary', 'overview'],
            $this->block()['known'],
        );
    }

    public function test_inherits_declares_row_cell_falls_back_to_edit_and_overview_to_summary(): void
    {
        $this->assertSame(
            ['row-cell' => ['edit'], 'overview' => ['summary']],
            $this->block()['inherits'],
        );
    }

    public function test_root_node_carries_the_class_level_list_item(): void
    {
        $root = $this->block()['byNode'][''];

        $this->assertSame([
            'list-item' => [
                'participates' => true,
                'widget' => 'contact-card',
            ],
        ], $root);
    }

    public function test_property_nodes_carry_their_per_context_maps(): void
    {
        $byNode = $this->block()['byNode'];

        $this->assertArrayHasKey('email', $byNode);
        $this->assertSame('email-input', $byNode['email']['edit']['widget']);
        $this->assertTrue($byNode['email']['row-cell']['participates']);

        $this->assertFalse($byNode['secret']['list-column']['participates']);
        $this->assertTrue($byNode['graph']['edit']['heavyweight']);
    }

    public function test_layout_is_null_when_no_layout_is_handed_in(): void
    {
        // The producer hands frame the resource's declared layout via ResourceDefinition;
        // omitting it leaves the field present-but-null, so the socket falls back to
        // SingleColumn (ticket 09/31).
        $this->assertArrayHasKey('layout', $this->block());
        $this->assertNull($this->block()['layout']);
    }

    public function test_layout_is_emitted_from_the_handed_in_definition_layout(): void
    {
        $block = (new ContextManifest)->forResource(ContactResourceData::class, 'subnav');

        $this->assertSame('subnav', $block['layout']);
    }

    public function test_unknown_context_throws(): void
    {
        $subject = new class
        {
            #[WidgetIn('bogus')]
            public string $field = '';
        };

        $this->expectException(InvalidArgumentException::class);

        (new ContextManifest)->forResource($subject::class);
    }

    public function test_list_item_on_a_property_throws(): void
    {
        $subject = new class
        {
            #[WidgetIn('list-item')]
            public string $field = '';
        };

        $this->expectException(InvalidArgumentException::class);

        (new ContextManifest)->forResource($subject::class);
    }

    public function test_per_property_context_at_class_level_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ContextManifest)->forResource(BadClassContextData::class);
    }

    // --- The collection grain: `summary` and `overview` (realm-dashboards ticket 01) ---

    public function test_class_level_summary_and_overview_project_to_the_root_pointer(): void
    {
        $root = (new ContextManifest)->forResource(SummaryOverviewResourceData::class)['byNode'][''];

        $this->assertSame([
            'summary' => [
                'participates' => true,
                'widget' => 'stat-row',
            ],
            'overview' => [
                'participates' => true,
                'widget' => 'figure-card',
                'options' => ['period' => 'month', 'recent' => 5],
            ],
        ], $root);
    }

    public function test_summary_can_opt_out_and_overview_can_refuse_the_cascade(): void
    {
        $subject = new #[Summary(false)] #[Overview(inheritsBinding: false)] class {};

        $root = (new ContextManifest)->forResource($subject::class)['byNode'][''];

        $this->assertSame(['participates' => false], $root['summary']);
        $this->assertSame(['participates' => true, 'inheritsBinding' => false], $root['overview']);
    }

    public function test_summary_on_a_property_throws(): void
    {
        $subject = new class
        {
            #[WidgetIn('summary', 'stat-row')]
            public string $field = '';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[summary] is whole-collection (class-level) only');

        (new ContextManifest)->forResource($subject::class);
    }

    public function test_overview_on_a_property_throws(): void
    {
        $subject = new class
        {
            #[WidgetIn('overview')]
            public string $field = '';
        };

        $this->expectException(InvalidArgumentException::class);

        (new ContextManifest)->forResource($subject::class);
    }

    public function test_class_level_row_actions_projects_as_a_root_list_column_entry(): void
    {
        $root = (new ContextManifest)->forResource(RowActionsResourceData::class)['byNode'][''];

        $this->assertSame([
            'list-column' => [
                'participates' => true,
                'widget' => 'row-actions',
                'options' => ['actions' => ['publish', 'archive']],
            ],
        ], $root);
    }

    // --- The optional ResourceContextContributor plug (particle-contribution-seam ticket 19) ---
    //
    // Frame learns only THAT a key has extra participation. It never learns why, who added it, or
    // that a "contribution" is a thing — so these tests exercise a port, not a producer.

    public function test_no_contributor_bound_leaves_the_block_untouched(): void
    {
        $this->assertSame(
            (new ContextManifest)->forResource(ContactResourceData::class),
            (new ContextManifest)->forResource(ContactResourceData::class, null, 'contacts'),
        );
    }

    public function test_a_contributor_with_no_nodes_for_the_key_leaves_the_block_untouched(): void
    {
        $manifest = new ContextManifest($this->contributor(['orders' => ['x.y' => []]]));

        $this->assertSame(
            (new ContextManifest)->forResource(ContactResourceData::class),
            $manifest->forResource(ContactResourceData::class, null, 'contacts'),
        );
    }

    public function test_a_contributor_adds_dotted_nodes_for_its_key(): void
    {
        $entry = ['list-column' => ['participates' => true, 'label' => 'Plan', 'sort' => 10]];

        $byNode = (new ContextManifest($this->contributor(['contacts' => ['commerce.plan' => $entry]])))
            ->forResource(ContactResourceData::class, null, 'contacts')['byNode'];

        $this->assertSame($entry, $byNode['commerce.plan']);
    }

    public function test_the_key_is_required_for_the_plug_to_fire(): void
    {
        $manifest = new ContextManifest($this->contributor(['contacts' => ['commerce.plan' => ['x' => []]]]));

        $this->assertArrayNotHasKey('commerce.plan', $manifest->forResource(ContactResourceData::class)['byNode']);
    }

    public function test_reflected_nodes_survive_a_contributor(): void
    {
        $reflected = (new ContextManifest)->forResource(ContactResourceData::class)['byNode'];

        $byNode = (new ContextManifest($this->contributor(['contacts' => ['commerce.plan' => ['x' => []]]])))
            ->forResource(ContactResourceData::class, null, 'contacts')['byNode'];

        foreach ($reflected as $pointer => $map) {
            $this->assertSame($map, $byNode[$pointer]);
        }
    }

    public function test_a_contributor_at_the_root_pointer_cannot_clobber_the_reflected_class_level_entries(): void
    {
        // The root pointer is the one namespace reflection and the plug SHARE: a contributor
        // returning "" used to replace the whole reflected class-level map under a
        // pointer-level merge. The merge is per pointer, so a contributed context is ADDED
        // and a reflected one keeps the class's own entry.
        $contributed = [
            'list-item' => ['participates' => true, 'widget' => 'contributed-card'],
            'summary' => ['participates' => true, 'widget' => 'stat-row'],
        ];

        $root = (new ContextManifest($this->contributor(['contacts' => ['' => $contributed]])))
            ->forResource(ContactResourceData::class, null, 'contacts')['byNode'][''];

        $this->assertSame(['participates' => true, 'widget' => 'contact-card'], $root['list-item']);
        $this->assertSame(['participates' => true, 'widget' => 'stat-row'], $root['summary']);
    }

    public function test_a_contributor_at_a_property_pointer_cannot_clobber_the_reflected_context(): void
    {
        $contributed = [
            'edit' => ['participates' => true, 'widget' => 'contributed-input'],
            'detail' => ['participates' => true, 'widget' => 'contributed-detail'],
        ];

        $email = (new ContextManifest($this->contributor(['contacts' => ['email' => $contributed]])))
            ->forResource(ContactResourceData::class, null, 'contacts')['byNode']['email'];

        $this->assertSame('email-input', $email['edit']['widget']);
        $this->assertSame('contributed-detail', $email['detail']['widget']);
        $this->assertTrue($email['row-cell']['participates']);
    }

    /** @param  array<string, array<string, array<string, mixed>>>  $nodes */
    private function contributor(array $nodes): ResourceContextContributor
    {
        return new class($nodes) implements ResourceContextContributor
        {
            public function __construct(private array $nodes) {}

            public function nodesFor(string $key): array
            {
                return $this->nodes[$key] ?? [];
            }
        };
    }
}
