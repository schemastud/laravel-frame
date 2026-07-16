<?php

namespace Schemastud\Frame\Tests;

use Schemastud\Frame\Registry\AliasEntry;
use Schemastud\Frame\Registry\RouteContextEntry;

/**
 * FC-22 — the RouteContext DTOs (the router half of the one-spine runtime). Frame
 * owns the flat descriptor shape; a host supplies the content. These lock the wire
 * shape a host emits into the manifest and the JS RouteRegistry consumes.
 */
class RouteContextEntryTest extends TestCase
{
    public function test_a_flat_route_entry_serializes_its_descriptor(): void
    {
        $entry = new RouteContextEntry(
            routeName: 'circuits.index',
            path: 'circuits',
            shell: 'app',
            lazy: false,
            guard: null,
            mounts: 'list',
            resource: 'circuits',
        );

        $array = $entry->toArray();

        $this->assertSame('circuits.index', $array['routeName']);
        $this->assertSame('circuits', $array['path']);
        $this->assertSame('app', $array['shell']);
        $this->assertFalse($array['lazy']);
        $this->assertNull($array['guard']);
        $this->assertSame('list', $array['mounts']);
        $this->assertSame('circuits', $array['resource']);
    }

    public function test_a_heavyweight_widget_mount_carries_its_widget_and_lazy_flag(): void
    {
        $entry = new RouteContextEntry(
            routeName: 'circuits.edit',
            path: 'circuits/:id',
            shell: 'app',
            lazy: true,
            guard: null,
            mounts: 'widget',
            widget: 'circuit-graph',
            resource: 'circuits',
        );

        $array = $entry->toArray();

        $this->assertSame('widget', $array['mounts']);
        $this->assertSame('circuit-graph', $array['widget']);
        $this->assertTrue($array['lazy']);
    }

    public function test_a_guarded_standalone_page_needs_no_resource(): void
    {
        $entry = new RouteContextEntry(
            routeName: 'admin.tenants.index',
            path: 'admin/tenants',
            shell: 'app',
            guard: 'root',
            mounts: 'list',
            resource: 'tenants',
        );

        $this->assertSame('root', $entry->toArray()['guard']);
    }

    public function test_alias_entry_serializes_the_three_flat_shapes(): void
    {
        $static = new AliasEntry(from: '/review-queue', to: '/review');
        $param = new AliasEntry(from: '/assistants/:id', to: '/threads/assistants/:id');
        $query = new AliasEntry(from: '/circuit-runs', to: '/system', preserveQuery: true);

        $this->assertSame(['from' => '/review-queue', 'to' => '/review', 'preserveQuery' => false], $static->toArray());
        $this->assertSame('/threads/assistants/:id', $param->toArray()['to']);
        $this->assertTrue($query->toArray()['preserveQuery']);
    }
}
