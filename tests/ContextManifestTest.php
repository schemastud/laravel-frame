<?php

namespace Schemastud\Frame\Tests;

use InvalidArgumentException;
use Schemastud\Frame\Attributes\WidgetIn;
use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Tests\Fixtures\BadClassContextData;
use Schemastud\Frame\Tests\Fixtures\ContactResourceData;
use Schemastud\Frame\Tests\Fixtures\RowActionsResourceData;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

/**
 * The {byNode, inherits, known} render-context block for one resource's Data class —
 * root ("") carries class-level list-item/row-actions, each property carries its
 * per-context map. Shares projection + validation with the strategy.
 */
class ContextManifestTest extends TestCase
{
    private function block(): array
    {
        return (new ContextManifest)->forResource(ContactResourceData::class);
    }

    public function test_known_is_the_closed_five_context_enum(): void
    {
        $this->assertSame(
            ['edit', 'detail', 'list-column', 'list-item', 'row-cell'],
            $this->block()['known'],
        );
    }

    public function test_inherits_declares_row_cell_falls_back_to_edit(): void
    {
        $this->assertSame(['row-cell' => ['edit']], $this->block()['inherits']);
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

    public function test_layout_is_null_when_the_resource_declares_none(): void
    {
        // ContactResourceData carries no #[AdminResource(layout: …)] — the field is
        // present but null, so the socket falls back to SingleColumn (ticket 09/31).
        $this->assertArrayHasKey('layout', $this->block());
        $this->assertNull($this->block()['layout']);
    }

    public function test_layout_is_emitted_from_the_admin_resource_attribute(): void
    {
        $block = (new ContextManifest)->forResource(SampleResourceData::class);

        $this->assertSame('subnav', $block['layout']);
    }

    public function test_invalid_layout_on_the_attribute_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new \Schemastud\Frame\Attributes\AdminResource(
            key: 'x',
            label: 'X',
            model: SampleModel::class,
            layout: 'bogus',
        );
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
}
