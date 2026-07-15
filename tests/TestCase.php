<?php

namespace Schemastud\Frame\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
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
            LaravelDataServiceProvider::class,
            LaravelDataSchemasServiceProvider::class,
            FrameServiceProvider::class,
        ];
    }
}
