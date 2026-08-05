<?php

namespace Schemastud\Frame\Tests;

use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

/**
 * RDU-01 — the agnostic {@see ResourceDefinition::with()} copy-wither: a nullable-field overlay that
 * returns an immutable copy, rebuilding the nested {@see NavMetadata} field-by-field. No realm concept
 * enters frame — the wither is realm-agnostic.
 */
class ResourceDefinitionWithTest extends TestCase
{
    private function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: 'sample',
            sourceKind: 'model',
            model: SampleModel::class,
            source: null,
            data: SampleResourceData::class,
            creatable: true,
            query: null,
            editData: null,
            policy: 'sample.view',
            form: 'bare',
            nav: new NavMetadata(
                label: 'Samples',
                group: 'Testing',
                icon: 'beaker',
                section: 'lab',
                navOrder: 3,
                routeName: 'samples.index',
            ),
            layout: 'single',
        );
    }

    public function test_with_no_arguments_returns_an_equal_but_distinct_copy(): void
    {
        $original = $this->definition();
        $copy = $original->withOverrides();

        $this->assertNotSame($original, $copy, 'the wither returns a new instance');
        $this->assertNotSame($original->nav, $copy->nav, 'the nav is rebuilt fresh, not shared');

        $this->assertSame($original->key, $copy->key);
        $this->assertSame($original->policy, $copy->policy);
        $this->assertSame($original->form, $copy->form);
        $this->assertSame($original->layout, $copy->layout);
        $this->assertSame($original->nav->label, $copy->nav->label);
        $this->assertSame($original->nav->navOrder, $copy->nav->navOrder);
    }

    public function test_a_top_level_overlay_replaces_only_the_named_field(): void
    {
        $original = $this->definition();
        $copy = $original->withOverrides(policy: 'sample.manage', showable: false, form: 'enriched');

        $this->assertSame('sample.manage', $copy->policy);
        $this->assertSame('enriched', $copy->form);
        $this->assertFalse($copy->showable);

        // Untouched fields carry through.
        $this->assertSame('sample', $copy->key);
        $this->assertTrue($copy->creatable);

        // The original is unmutated.
        $this->assertSame('sample.view', $original->policy);
        $this->assertSame('bare', $original->form);
        $this->assertTrue($original->showable);
    }

    public function test_a_nav_overlay_rebuilds_navmetadata_field_by_field(): void
    {
        $original = $this->definition();
        $copy = $original->withOverrides(label: 'Widgets', navOrder: 9);

        $this->assertSame('Widgets', $copy->nav->label);
        $this->assertSame(9, $copy->nav->navOrder);

        // Non-overlaid nav fields survive.
        $this->assertSame('Testing', $copy->nav->group);
        $this->assertSame('beaker', $copy->nav->icon);
        $this->assertSame('lab', $copy->nav->section);
        $this->assertSame('samples.index', $copy->nav->routeName);

        // The original nav is unmutated.
        $this->assertSame('Samples', $original->nav->label);
        $this->assertSame(3, $original->nav->navOrder);
    }
}
