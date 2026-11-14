<?php

namespace Schemastud\Frame\Tests;

use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Schemastud\Frame\Http\Controllers\FrameManifestController;
use Schemastud\Frame\Routing\FrameRoutes;

class LateFrameHostProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function (): void {
            $this->app['router']->get('late/tenant/manifest', [FrameManifestController::class, '__invoke'])
                ->middleware(['auth:sanctum', 'tenant-membership'])->name('stud.frame.manifest');
        });
    }
}

class AutomaticRouteNamesTest extends TestCase
{
    protected bool $lateHost = false;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ...($this->lateHost ? [LateFrameHostProvider::class] : [])];
    }

    protected function freshRoutes(): void
    {
        app('router')->setRoutes(new RouteCollection);
    }

    protected function automatic(): void
    {
        require __DIR__.'/../routes/frame.php';
    }

    protected function host(string $name): void
    {
        Route::get('tenant/manifest', [FrameManifestController::class, '__invoke'])
            ->middleware(['auth:sanctum', 'tenant-membership'])->name($name);
    }

    public function test_refuses_a_host_manifest_registered_before_the_automatic_mount(): void
    {
        $this->freshRoutes();
        $this->host('stud.frame.manifest');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('stud.frame.manifest');
        $this->automatic();
    }

    public static function automaticNames(): array
    {
        $rows = [];
        foreach (['frame.', 'stud.frame.'] as $stem) {
            foreach (['manifest', 'resources.index', 'resources.schema', 'resources.actions.schema', 'resources.show', 'resources.store', 'resources.update', 'resources.destroy', 'resources.filters.schema', 'resources.filters.options', 'resources.filters.variants', 'resources.filters.variant-schema', 'resources.summary'] as $suffix) {
                $rows[$stem.$suffix] = [$stem.$suffix];
            }
        }

        return $rows;
    }

    #[DataProvider('automaticNames')]
    public function test_final_validator_refuses_every_exact_name_when_host_registers_later(string $name): void
    {
        $this->freshRoutes();
        $this->automatic();
        $this->host($name);
        app('router')->getRoutes()->refreshNameLookups();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($name);
        FrameRoutes::validateNames();
    }

    public function test_normal_bootstrap_validation_catches_a_late_host_booted_callback(): void
    {
        $this->lateHost = true;
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('late/tenant/manifest');
        try {
            // Testbench boots providers directly. Finish through Laravel's normal bootstrap boundary
            // after the fresh app has run Frame and the late provider's real booted callback.
            $fresh = $this->createApplication();
            $fresh->bootstrapWith([BootProviders::class]);
        } finally {
            \Illuminate\Container\Container::setInstance($this->app);
            \Illuminate\Support\Facades\Facade::clearResolvedInstances();
            \Illuminate\Support\Facades\Facade::setFacadeApplication($this->app);
        }
    }

    public function test_diagnostic_describes_both_exposures_and_the_host_remedy(): void
    {
        $this->freshRoutes();
        $this->automatic();
        $this->host('stud.frame.manifest');
        try {
            FrameRoutes::validateNames();
            $this->fail('Expected the duplicate name to refuse.');
        } catch (LogicException $error) {
            foreach (['stud.frame.manifest', 'stud/frame/manifest', 'tenant/manifest', 'GET', 'HEAD', FrameManifestController::class.'@__invoke', 'web', 'auth:sanctum', 'tenant-membership', 'frame.register_route=false', 'explicitly'] as $part) {
                $this->assertStringContainsString($part, $error->getMessage());
            }
        }
    }

    public function test_idempotent_reuse_and_valid_compiled_routes_remain_valid(): void
    {
        $this->freshRoutes();
        $this->automatic();
        $count = count(app('router')->getRoutes());
        $this->automatic();
        FrameRoutes::mount();
        FrameRoutes::validateNames();
        $this->assertCount($count, app('router')->getRoutes());
        app('router')->setCompiledRoutes(app('router')->getRoutes()->compile());
        FrameRoutes::validateNames();
        FrameRoutes::mount();
        $this->assertSame('stud.frame.manifest', app('router')->getRoutes()->match(Request::create('/stud/frame/manifest'))->getName());
    }

    public function test_route_cache_still_refuses_a_duplicate_added_by_the_host(): void
    {
        $this->freshRoutes();
        $this->automatic();
        $this->host('stud.frame.manifest');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Another route has already been assigned name [stud.frame.manifest]');
        app('router')->getRoutes()->compile();
    }

    public function test_frame_console_is_not_a_reserved_prefix_collision(): void
    {
        $this->freshRoutes();
        $this->host('frame.console');
        $this->automatic();
        FrameRoutes::validateNames();
        $this->app->bootstrapWith([BootProviders::class]);
        $this->assertCount(27, app('router')->getRoutes());
    }

    public function test_disabling_automatic_registration_leaves_manual_names_to_the_host(): void
    {
        config(['frame.register_route' => false]);
        $this->host('stud.frame.manifest');
        FrameRoutes::validateNames();
        $this->assertTrue(true);
    }

    public function test_reusing_an_identical_host_exposure_still_protects_its_actual_name(): void
    {
        $this->freshRoutes();
        FrameRoutes::mount();
        $this->automatic();
        $this->host('stud.frame.manifest');
        $this->expectException(LogicException::class);
        FrameRoutes::validateNames();
    }

    public function test_validator_detects_a_host_duplicate_added_after_loading_compiled_routes(): void
    {
        $this->freshRoutes();
        $this->automatic();
        app('router')->setCompiledRoutes(app('router')->getRoutes()->compile());
        $this->host('stud.frame.manifest');
        $this->expectException(LogicException::class);
        FrameRoutes::validateNames();
    }
}
