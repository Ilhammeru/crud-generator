<?php

namespace Zola\CrudGenerator;

use Illuminate\Support\ServiceProvider;
use Zola\CrudGenerator\Console\Commands\ModelGenerator;
use Zola\CrudGenerator\Console\Commands\RepositoryGenerator;
use Zola\CrudGenerator\Console\Commands\ServiceGenerator;

class CrudGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config so the host app always has defaults.
        // The config key MUST match the file name ('crud-generator') so that
        // config('crud-generator.*') resolves in the host app.
        $this->mergeConfigFrom(__DIR__ . '/../config/crud-generator.php', 'crud-generator');

        // Bind your main class into the container.
        $this->app->singleton(CrudGenerator::class, fn() => new CrudGenerator());
    }

    public function boot(): void
    {
        // Make config publishable into the host app.
        $this->publishes([
            __DIR__ . '/../config/crud-generator.php' => config_path('crud-generator.php'),
        ], 'crud-generator-config');

        // When you add them later:
        // $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        // $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        // $this->loadViewsFrom(__DIR__ . '/../resources/views', 'toolkit');

        if ($this->app->runningInConsole()) {
            // $this->commands([\Personal\Toolkit\Commands\DoThing::class]);
            $this->commands([
                ModelGenerator::class,
                RepositoryGenerator::class,
                ServiceGenerator::class
            ]);
        }
    }
}
