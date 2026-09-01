<?php

namespace Schemastud\Frame\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\Popcorn\Laravel\PopcornServiceProvider;
use Schemastud\DataSchemas\LaravelDataSchemasServiceProvider;
use Schemastud\Frame\FrameServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getEnvironmentSetUp($app): void
    {
        // The default manifest route rides the `web` group, whose cookie
        // encryption needs an app key.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            // Testbench does not auto-discover, so this provider — which installs the SHARED
            // RegistryIndex binding and the baked membership — never boots unless it is named here.
            // Without it every index read in this suite lands on a fresh throwaway and passes over
            // an empty index (registry-kernel 27 D3).
            PopcornServiceProvider::class,
            LaravelDataServiceProvider::class,
            LaravelDataSchemasServiceProvider::class,
            FrameServiceProvider::class,
        ];
    }
}
