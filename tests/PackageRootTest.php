<?php

namespace Schemastud\Frame\Tests;

use Illuminate\Http\Request;
use Schemastud\Frame\Routing\FrameRoutes;

class PackageRootTest extends TestCase
{
    public function test_provider_dual_mounts_matching_legacy_and_stud_routes_without_duplicate_manual_mounts(): void
    {
        $routes = app('router')->getRoutes();
        $old = collect($routes->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'frame/'));
        $this->assertGreaterThan(10, $old->count());
        foreach ($old as $legacy) {
            $new = collect($routes->getRoutes())->first(fn ($r) => $r->uri() === 'stud/'.$legacy->uri() && $r->methods() === $legacy->methods());
            $this->assertNotNull($new);
            $this->assertSame($legacy->getActionName(), $new->getActionName());
            $this->assertSame($legacy->gatherMiddleware(), $new->gatherMiddleware());
        }
        $count = count($routes);
        FrameRoutes::mount();
        FrameRoutes::mount();
        $this->assertCount($count, $routes);
        $this->assertIsArray($routes->compile());
    }

    public function test_override_adds_a_twin_and_equal_legacy_root_reuses_the_same_exposure(): void
    {
        $routes = app('router')->getRoutes();
        config(['frame.api_root' => 'custom/editor']);
        FrameRoutes::mount();
        $new = $routes->match(Request::create('/custom/editor/manifest'));
        $this->assertSame($routes->match(Request::create('/frame/manifest'))->getActionName(), $new->getActionName());
        $count = count($routes);
        config(['frame.api_root' => 'frame']);
        FrameRoutes::mount();
        $this->assertCount($count, $routes);
    }

    public function test_idempotence_against_a_real_compiled_route_collection(): void
    {
        $router = app('router');
        $router->setCompiledRoutes($router->getRoutes()->compile());
        $this->assertInstanceOf(\Illuminate\Routing\CompiledRouteCollection::class, $router->getRoutes());
        $count = count($router->getRoutes());
        FrameRoutes::legacy();
        FrameRoutes::mount();
        $this->assertCount($count, $router->getRoutes());
        $this->assertSame('stud.frame.manifest', $router->getRoutes()->match(Request::create('/stud/frame/manifest'))->getName());
    }

    public function test_a_conflicting_exposure_refuses_by_name(): void
    {
        config(['frame.api_root' => 'conflicting/frame']);
        \Illuminate\Support\Facades\Route::get('conflicting/frame/manifest', fn () => [])->name('foreign.manifest');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('foreign.manifest');
        FrameRoutes::mount();
    }

    public function test_an_empty_root_refuses(): void
    {
        config(['frame.api_root' => '/']);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Frame API root must not be empty');
        FrameRoutes::mount();
    }

    public function test_disabling_resources_keeps_only_the_manifest_at_each_new_exposure(): void
    {
        config(['frame.register_resource_routes' => false, 'frame.api_root' => 'limited/current', 'frame.route_prefix' => 'limited/legacy']);
        FrameRoutes::legacy();
        FrameRoutes::mount();
        $rows = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'limited/'));
        $this->assertCount(2, $rows);
        $this->assertSame(['limited/legacy/manifest', 'limited/current/manifest'], $rows->map(fn ($r) => $r->uri())->values()->all());
    }

    public function test_duplicate_guard_does_not_freeze_later_host_middleware(): void
    {
        $routes = app('router')->getRoutes();
        $manifest = $routes->match(Request::create('/stud/frame/manifest'));
        $filters = $routes->match(Request::create('/stud/frame/resources/example/filters/schema'));
        FrameRoutes::mount();
        foreach ([$manifest, $filters] as $route) {
            $route->middleware('after-frame-mount');
            $this->assertContains('after-frame-mount', $route->gatherMiddleware());
        }
    }
}
