<?php

namespace Schemastud\Frame\Routing;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use LogicException;
use Schemastud\Frame\Http\Controllers\FrameManifestController;
use Schemastud\Frame\Http\Controllers\FrameResourceController;

/** Frame's foundation-owned root; no dependency on a consuming engine. */
final class FrameRoutes
{
    public static function mount(): void
    {
        self::atRoot((string) config('frame.api_root'), 'stud.frame.');
    }

    /** @internal Retained provider exposure until owner-review ticket 16. */
    public static function legacy(): void
    {
        self::atRoot((string) config('frame.route_prefix'), 'frame.');
    }

    private static function atRoot(string $root, string $names): void
    {
        if (trim($root, '/') === '') {
            throw new LogicException('Frame API root must not be empty.');
        }
        Route::middleware((array) config('frame.middleware', ['web']))->prefix(trim($root, '/'))->group(function () use ($names): void {
            self::route(['GET', 'HEAD'], 'manifest', [FrameManifestController::class, '__invoke'], $names.'manifest');
            if (! config('frame.register_resource_routes', true)) {
                return;
            }
            foreach ([
                ['GET', 'resources/{resource}', 'index', 'resources.index'],
                ['GET', 'resources/{resource}/schema', 'schema', 'resources.schema'],
                ['GET', 'resources/{resource}/actions/{action}/schema', 'actionSchema', 'resources.actions.schema'],
                ['GET', 'resources/{resource}/records/{id}', 'show', 'resources.show'],
                ['POST', 'resources/{resource}', 'store', 'resources.store'],
                ['PUT', 'resources/{resource}/records/{id}', 'update', 'resources.update'],
                ['DELETE', 'resources/{resource}/records/{id}', 'destroy', 'resources.destroy'],
            ] as [$method, $uri, $action, $name]) {
                self::route($method === 'GET' ? ['GET', 'HEAD'] : [$method], $uri, [FrameResourceController::class, $action], $names.$name);
            }
            ResourceRoutes::filters(names: $names.'resources');
            ResourceRoutes::summary(names: $names.'resources');
        });
    }

    /** @internal Idempotence uses actual routes, including a cached collection, never process-local state. */
    public static function route(array $methods, string $uri, array $action, string $name, array $defaults = []): void
    {
        $router = app(Router::class);
        $groups = $router->getGroupStack();
        $group = $groups === [] ? [] : $groups[array_key_last($groups)];
        $path = trim(($group['prefix'] ?? '').'/'.$uri, '/');
        foreach ($router->getRoutes() as $existing) {
            if ($existing->uri() !== $path || $existing->getDomain() !== ($group['domain'] ?? null) || $existing->methods() !== $methods) {
                continue;
            }
            $actions = [$action[0].'@'.$action[1]];
            if ($action[1] === '__invoke') {
                $actions[] = $action[0];
            }
            if (in_array($existing->getActionName(), $actions, true)
                && Router::uniqueMiddleware($existing->middleware()) === Router::uniqueMiddleware((array) ($group['middleware'] ?? []))
                && $existing->defaults === $defaults) {
                return;
            }
            throw new LogicException('Frame exposure conflicts with existing route ['.($existing->getName() ?? $path).'].');
        }
        $route = $router->match($methods, $uri, $action)->name($name);
        foreach ($defaults as $key => $value) {
            $route->defaults($key, $value);
        }
    }
}
