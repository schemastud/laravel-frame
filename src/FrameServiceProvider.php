<?php

namespace Schemastud\Frame;

use Schemastud\Frame\Authorization\DenyingResourceActionAuthorizer;
use Schemastud\Frame\Authorization\OpenResourceAccessGate;
use Schemastud\Frame\Contracts\ResourceAccessGate;
use Schemastud\Frame\Contracts\ResourceActionAuthorizer;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\CompositeResourceRegistry;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Strategies\ReadOnlyAttributeStrategy;
use Schemastud\Frame\Strategies\ResourceRefStrategy;
use Schemastud\Frame\Strategies\WidgetAttributesStrategy;
use Schemastud\Frame\Strategies\WidgetContextsStrategy;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FrameServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-frame')
            ->hasConfigFile('frame');
    }

    public function packageRegistered(): void
    {
        $this->registerResourceRegistry();
        $this->registerResourceAccessGate();
    }

    public function packageBooted(): void
    {
        $this->registerSchemaStrategies();
        $this->registerManifestRoute();
    }

    /**
     * Bind the {@see ResourceRegistry} port onto the `frame.resources` INDEX, with frame's own
     * imperative store attached as its default member (registry-kernel 77).
     *
     * Before 77 frame bound nothing here and a producer `alias()`ed the port onto its own registry —
     * which is precisely how the container's answer and the index's answer came to be two different
     * objects, one of them empty. Binding the index means `app(ResourceRegistry::class)` and
     * `RegistryIndex::ownerOf('frame.resources')` are the SAME object at every host, and a producer
     * `attach()`es beside frame rather than displacing it.
     *
     * The default member is attached LAZILY — as a closure over the CONTAINER, never a `new` — so a
     * host whose producer answers for everything never constructs an empty store, and `frame.resources`
     * still enumerates the slot. The container half is load-bearing rather than stylistic: the index
     * deliberately does not memoise what a member closure returns (see
     * {@see CompositeResourceRegistry::reveal()}), so a closure that constructs would hand out a fresh
     * empty store on every read and silently lose every imperative `register()`.
     *
     * A host or test that genuinely wants the old shape can still `instance()` its own implementation
     * over this binding — `singleton()` loses to a later binding, which is what frame's own tests do.
     */
    protected function registerResourceRegistry(): void
    {
        $this->app->singleton(CompositeResourceRegistry::class, function (): CompositeResourceRegistry {
            return (new CompositeResourceRegistry)->attach(
                CompositeResourceRegistry::DEFAULT_MEMBER,
                new InMemoryResourceRegistry(CompositeResourceRegistry::DEFAULT_MEMBER),
                by: static::class,
            );
        });

        $this->app->alias(CompositeResourceRegistry::class, ResourceRegistry::class);
    }

    /**
     * Bind the {@see ResourceAccessGate} port to frame's permit-everything default.
     *
     * `bind()`, not `singleton()`: the producer above frame (beam) rebinds this at its own
     * `register()`, and the gate reads live config and the current actor on every call, so there is
     * nothing to memoise. Binding it here rather than leaving it unbound is what lets
     * {@see \Schemastud\Frame\Authorization\ResourceAuthorizer} take it as a constructor dependency
     * instead of probing `app()->bound()` — a gate you have to remember to ask is the shape this
     * exists to remove.
     */
    protected function registerResourceAccessGate(): void
    {
        $this->app->bind(ResourceAccessGate::class, OpenResourceAccessGate::class);
        // The action port, bound to frame's deny-everything default on the same terms: a producer that
        // projects actions (beam) rebinds it at its own `register()` with the rule its mounts enforce.
        $this->app->bind(ResourceActionAuthorizer::class, DenyingResourceActionAuthorizer::class);
    }

    /**
     * Append frame's property strategies to the laravel-data-schemas pipeline so
     * `#[Widget]` projects to `x-stud-widget` and `#[ReadOnlyField]` to `readOnly`.
     * Idempotent — guards against double-registration on re-boot (the data-filters
     * pattern).
     */
    protected function registerSchemaStrategies(): void
    {
        $strategies = config('data-schemas.strategies', []);

        foreach ([WidgetAttributesStrategy::class, ReadOnlyAttributeStrategy::class, WidgetContextsStrategy::class, ResourceRefStrategy::class] as $strategy) {
            if (! in_array($strategy, $strategies, true)) {
                $strategies[] = $strategy;
            }
        }

        config(['data-schemas.strategies' => $strategies]);
    }

    protected function registerManifestRoute(): void
    {
        if (config('frame.register_route', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/frame.php');
        }
    }
}
