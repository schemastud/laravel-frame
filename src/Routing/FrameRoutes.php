<?php

namespace Schemastud\Frame\Routing;

use Illuminate\Routing\Route as FrameworkRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use LogicException;
use Schemastud\Frame\Http\Controllers\FrameManifestController;
use Schemastud\Frame\Http\Controllers\FrameResourceController;

/** Frame's foundation-owned root; no dependency on a consuming engine. */
final class FrameRoutes
{
    private const AUTOMATIC = '_frame_automatic';

    /** @internal Provider-owned exposures; the marker survives route caching. */
    public static function automatic(): void
    {
        Route::group([self::AUTOMATIC => true], function (): void {
            self::legacy();
            self::mount();
        });
    }

    /** Validate the final collection, or call explicitly after adding dynamic routes. */
    public static function validateNames(): void
    {
        if (! config('frame.register_route', true)) {
            return;
        }

        // Materialize a cached collection once, then inspect it in one pass. A cached collection
        // also includes routes added at runtime, which still need the same collision check.
        $routes = app(Router::class)->getRoutes()->getRoutes();
        $seen = [];
        foreach ($routes as $route) {
            $name = $route->getName();
            if ($name === null) {
                continue;
            }
            if (isset($seen[$name]) && $seen[$name] !== $route
                && ($seen[$name]->getAction(self::AUTOMATIC) || $route->getAction(self::AUTOMATIC))) {
                throw new LogicException(self::collision($name, self::describe($seen[$name]), self::describe($route)));
            }
            $seen[$name] ??= $route;
        }
    }

    private static function describe(FrameworkRoute $route): string
    {
        return self::exposure($route->methods(), $route->uri(), $route->getDomain(), $route->getActionName(), $route->middleware());
    }

    private static function exposure(array $methods, string $uri, ?string $domain, string $action, array $middleware): string
    {
        return implode('|', $methods).' /'.$uri.' domain='.($domain ?? '*').' action='.$action.' middleware='.json_encode($middleware);
    }

    private static function collision(string $name, string $first, string $second): string
    {
        return 'Frame automatic route name collision ['.$name.']: '.$first.' conflicts with '.$second.'. Set frame.register_route=false and mount the required sockets explicitly inside the host boundary.';
    }

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
                if ($group[self::AUTOMATIC] ?? false) {
                    $existing->setAction(array_merge($existing->getAction(), [self::AUTOMATIC => true]));
                }

                return;
            }
            throw new LogicException('Frame exposure conflicts with existing route ['.($existing->getName() ?? $path).'].');
        }
        $qualifiedName = ($group['as'] ?? '').$name;
        if (config('frame.register_route', true)) {
            foreach ($router->getRoutes() as $existing) {
                if ($existing->getName() === $qualifiedName && (($group[self::AUTOMATIC] ?? false) || $existing->getAction(self::AUTOMATIC))) {
                    throw new LogicException(self::collision(
                        $qualifiedName,
                        self::describe($existing),
                        self::exposure($methods, $path, $group['domain'] ?? null, $action[0].'@'.$action[1], (array) ($group['middleware'] ?? [])),
                    ));
                }
            }
        }
        $route = $router->match($methods, $uri, $action)->name($name);
        foreach ($defaults as $key => $value) {
            $route->defaults($key, $value);
        }
    }
}
