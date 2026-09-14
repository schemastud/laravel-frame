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
            self::mount($at, 'filters/'.$suffix, [FrameResourceFiltersController::class, $method], $names.'.filters.'.$name, $defaults);
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
        self::mount($at, 'summary', [FrameResourceSummaryController::class, 'show'], $names.'.summary', $defaults);
    }

    /**
     * One GET under the resource root, named, carrying the host's route context.
     *
     * Every capability mounted here is the same three lines — trim the root, name the route, stamp the
     * host's `$defaults` onto it — and the two that existed had already been written twice. A capability
     * whose defaults loop is a copy is a capability whose route context can silently stop matching its
     * siblings'.
     *
     * @param  array{class-string, string}  $action
     * @param  array<string, mixed>  $defaults
     */
    private static function mount(string $at, string $suffix, array $action, string $name, array $defaults): void
    {
        $route = Route::get(rtrim($at, '/').'/'.$suffix, $action)->name($name);

        foreach ($defaults as $key => $value) {
            $route->defaults($key, $value);
        }
    }
}
