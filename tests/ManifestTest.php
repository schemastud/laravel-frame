<?php

namespace Schemastud\Frame\Tests;

use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

/**
 * Ticket 03 — frame's GENERIC manifest runtime: the {@see ResourceRegistry} port +
 * GET /frame/manifest serving {@see ResourceDefinition}[]. Frame holds definitions a
 * producer HANDS it (attribute discovery is the producer's concern, tested in beam);
 * here we register definitions directly through the agnostic in-memory registry.
 */
class ManifestTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Bind the agnostic in-memory registry as the port and hand it one definition
        // (the producer's job in a real host; here frame owns the whole surface).
        $app->singleton(ResourceRegistry::class, function () {
            return (new InMemoryResourceRegistry)->register($this->sampleDefinition());
        });
    }

    private function sampleDefinition(): ResourceDefinition
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
            policy: null,
            form: 'raw',
            nav: new NavMetadata(
                label: 'Samples',
                group: 'Testing',
                section: 'lab',
                navOrder: 3,
                routeName: 'samples.index',
            ),
        );
    }

    public function test_a_registered_definition_is_served_from_the_registry(): void
    {
        $registry = $this->app->make(ResourceRegistry::class);

        $this->assertTrue($registry->has('sample'));

        $def = $registry->get('sample');
        $this->assertInstanceOf(ResourceDefinition::class, $def);
        $this->assertSame(SampleResourceData::class, $def->data);
        $this->assertSame(SampleModel::class, $def->model);
        $this->assertSame('raw', $def->form);
        $this->assertSame('Samples', $def->nav->label);
        $this->assertSame('Testing', $def->nav->group);
        // FC-22 host-nav join keys ride NavMetadata.
        $this->assertSame('lab', $def->nav->section);
        $this->assertSame(3, $def->nav->navOrder);
        $this->assertSame('samples.index', $def->nav->routeName);
    }

    public function test_register_adds_a_definition(): void
    {
        $registry = $this->app->make(ResourceRegistry::class);

        $registry->register(new ResourceDefinition(
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

    public function test_get_frame_manifest_returns_resource_definitions(): void
    {
        $response = $this->getJson('/frame/manifest');

        $response->assertOk();
        $response->assertJsonPath('resources.0.key', 'sample');
        $response->assertJsonPath('resources.0.form', 'raw');
        $response->assertJsonPath('resources.0.nav.label', 'Samples');
        $response->assertJsonPath('resources.0.data', SampleResourceData::class);
    }

    public function test_definitions_register_as_flat_siblings(): void
    {
        $registry = $this->app->make(ResourceRegistry::class);

        $registry->register(new ResourceDefinition(
            key: 'layers',
            sourceKind: 'model',
            model: SampleModel::class,
            source: null,
            data: SampleResourceData::class,
            creatable: true,
            query: null, editData: null, policy: null, form: 'raw',
            nav: new NavMetadata(label: 'Layers'),
        ));
        $registry->register(new ResourceDefinition(
            key: 'layer-nodes',
            sourceKind: 'model',
            model: SampleModel::class,
            source: null,
            data: SampleResourceData::class,
            creatable: true,
            query: null, editData: null, policy: null, form: 'raw',
            nav: new NavMetadata(label: 'Layer Nodes'),
        ));

        // Flat siblings, no nesting/router in the manifest.
        $keys = array_map(fn (ResourceDefinition $d) => $d->key, $registry->all());
        $this->assertContains('layers', $keys);
        $this->assertContains('layer-nodes', $keys);
    }
}
