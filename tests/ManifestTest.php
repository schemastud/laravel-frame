<?php

namespace Schemastud\Frame\Tests;

use Schemastud\Frame\Registry\AdminResourceDefinition;
use Schemastud\Frame\Registry\AdminResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

/**
 * Ticket 03 — the manifest runtime: boot-time #[AdminResource] discovery, the
 * ->register() escape hatch, and GET /frame/manifest serving AdminResourceDefinition[].
 */
class ManifestTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Register the annotated sample via the config discovery path (the boot scan).
        $app['config']->set('frame.resources', [SampleResourceData::class]);
    }

    public function test_admin_resource_annotated_class_is_discovered_by_the_boot_scan(): void
    {
        $registry = $this->app->make(AdminResourceRegistry::class);

        $this->assertTrue($registry->has('sample'));

        $def = $registry->get('sample');
        $this->assertInstanceOf(AdminResourceDefinition::class, $def);
        $this->assertSame(SampleResourceData::class, $def->data);
        $this->assertSame(SampleModel::class, $def->model);
        $this->assertSame('raw', $def->form);
        $this->assertSame('Samples', $def->nav->label);
        $this->assertSame('Testing', $def->nav->group);
        // FC-22 host-nav join keys flow from the attribute to NavMetadata.
        $this->assertSame('lab', $def->nav->section);
        $this->assertSame(3, $def->nav->navOrder);
        $this->assertSame('samples.index', $def->nav->routeName);
    }

    public function test_register_escape_hatch_adds_an_attribute_less_resource(): void
    {
        $registry = $this->app->make(AdminResourceRegistry::class);

        $registry->register(new AdminResourceDefinition(
            key: 'layer-nodes',
            sourceKind: 'model',
            model: SampleModel::class,
            source: null,
            data: SampleResourceData::class,
            creatable: true,
            query: null,
            editData: null,
            policy: null,
            form: 'raw',
            nav: new NavMetadata(label: 'Layer Nodes', group: 'Numerology'),
        ));

        $this->assertTrue($registry->has('layer-nodes'));
        $this->assertSame('Layer Nodes', $registry->get('layer-nodes')->nav->label);
    }

    public function test_get_frame_manifest_returns_admin_resource_definitions(): void
    {
        $response = $this->getJson('/frame/manifest');

        $response->assertOk();
        $response->assertJsonPath('resources.0.key', 'sample');
        $response->assertJsonPath('resources.0.form', 'raw');
        $response->assertJsonPath('resources.0.nav.label', 'Samples');
        $response->assertJsonPath('resources.0.data', SampleResourceData::class);
    }

    public function test_nested_resources_register_as_flat_siblings(): void
    {
        $registry = $this->app->make(AdminResourceRegistry::class);

        $registry->register(new AdminResourceDefinition(
            key: 'layers',
            sourceKind: 'model',
            model: SampleModel::class,
            source: null,
            data: SampleResourceData::class,
            creatable: true,
            query: null, editData: null, policy: null, form: 'raw',
            nav: new NavMetadata(label: 'Layers'),
        ));
        $registry->register(new AdminResourceDefinition(
            key: 'layer-nodes',
            sourceKind: 'model',
            model: SampleModel::class,
            source: null,
            data: SampleResourceData::class,
            creatable: true,
            query: null, editData: null, policy: null, form: 'raw',
            nav: new NavMetadata(label: 'Layer Nodes'),
        ));

        // Two flat siblings, no nesting/router in the manifest.
        $keys = array_map(fn (AdminResourceDefinition $d) => $d->key, $registry->all());
        $this->assertContains('layers', $keys);
        $this->assertContains('layer-nodes', $keys);
    }
}
