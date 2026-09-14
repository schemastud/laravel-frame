<?php

namespace Schemastud\Frame\Routing;

use Illuminate\Support\Facades\Route;
use Schemastud\Frame\Http\Controllers\FrameResourceFiltersController;
use Schemastud\Frame\Http\Controllers\FrameResourceSummaryController;

/** Mount the resource capability interface inside a host's chosen exposure. */
class ResourceRoutes
{
    /** @param array<string, mixed> $defaults Host-owned route context. */
    public static function filters(
        string $at = 'resources/{resource}',
        string $names = 'frame.resources',
        array $defaults = [],
    ): void {
        foreach (['schema' => 'schema', 'options/{ref}' => 'options', 'variants' => 'variants', '{variant}/schema' => 'schema'] as $suffix => $method) {
            $name = $suffix === '{variant}/schema' ? 'variant-schema' : $method;
            $route = Route::get(rtrim($at, '/').'/filters/'.$suffix, [FrameResourceFiltersController::class, $method])
                ->name($names.'.filters.'.$name);
            foreach ($defaults as $key => $value) {
                $route->defaults($key, $value);
            }
        }
    }

    /**
     * Mount the summary capability beside the filters — one GET under the resource root, gated by the
     * same resource-access authorizer.
     *
     * @param  array<string, mixed>  $defaults  Host-owned route context.
     */
    public static function summary(
        string $at = 'resources/{resource}',
        string $names = 'frame.resources',
        array $defaults = [],
    ): void {
        $route = Route::get(rtrim($at, '/').'/summary', [FrameResourceSummaryController::class, 'show'])
            ->name($names.'.summary');
        foreach ($defaults as $key => $value) {
            $route->defaults($key, $value);
        }
    }
}
