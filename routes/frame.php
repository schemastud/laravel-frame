<?php

use Illuminate\Support\Facades\Route;
use Schemastud\Frame\Http\Controllers\FrameManifestController;

Route::middleware(config('frame.middleware', ['web']))
    ->prefix(config('frame.route_prefix', 'frame'))
    ->group(function () {
        Route::get('manifest', FrameManifestController::class)->name('frame.manifest');
    });
