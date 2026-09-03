<?php

namespace Zola\CrudGenerator\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Zola\CrudGenerator\CrudGeneratorServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Register the package's service provider inside the throwaway
     * Testbench application, so the config, bindings and Artisan
     * commands are all available to the tests.
     */
    protected function getPackageProviders($app): array
    {
        return [
            CrudGeneratorServiceProvider::class,
        ];
    }
}
