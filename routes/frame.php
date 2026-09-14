<?php

use Illuminate\Support\Facades\Route;
use Schemastud\Frame\Http\Controllers\FrameManifestController;
use Schemastud\Frame\Http\Controllers\FrameResourceController;
use Schemastud\Frame\Routing\ResourceRoutes;

/*
 * Frame's server surface, all under one configurable prefix + middleware so a host moves
 * the whole CMS by flipping `frame.route_prefix` (e.g. 'frame' → '~/beam') and gates it by
 * setting `frame.middleware`. The manifest is always registered; the resource CRUD + facets
 * socket is opt-out via `frame.register_resource_routes` (a host that still hand-rolls its
 * own resource endpoints sets it false). The socket resolves its per-resource plug through
 * the host-bound FrameResourceHandlerResolver and each resource's declared filter provider.
 */
Route::middleware(config('frame.middleware', ['web']))
    ->prefix(config('frame.route_prefix', 'frame'))
    ->group(function () {
        Route::get('manifest', FrameManifestController::class)->name('frame.manifest');

        if (config('frame.register_resource_routes', true)) {
            Route::get('resources/{resource}', [FrameResourceController::class, 'index'])->name('frame.resources.index');
            Route::get('resources/{resource}/schema', [FrameResourceController::class, 'schema'])->name('frame.resources.schema');
            Route::get('resources/{resource}/records/{id}', [FrameResourceController::class, 'show'])->name('frame.resources.show');
            Route::post('resources/{resource}', [FrameResourceController::class, 'store'])->name('frame.resources.store');
            Route::put('resources/{resource}/records/{id}', [FrameResourceController::class, 'update'])->name('frame.resources.update');
            Route::delete('resources/{resource}/records/{id}', [FrameResourceController::class, 'destroy'])->name('frame.resources.destroy');

            ResourceRoutes::filters();
        }
    });
