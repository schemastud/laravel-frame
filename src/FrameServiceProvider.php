<?php

namespace Schemastud\Frame;

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

    public function packageBooted(): void
    {
        $this->registerSchemaStrategies();
        $this->registerManifestRoute();
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
