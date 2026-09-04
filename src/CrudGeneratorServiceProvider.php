<?php

namespace Zola\CrudGenerator;

use Illuminate\Support\ServiceProvider;
use Zola\CrudGenerator\Console\Commands\ControllerGenerator;
use Zola\CrudGenerator\Console\Commands\CrudGenerator;
use Zola\CrudGenerator\Console\Commands\DataGenerator;
use Zola\CrudGenerator\Console\Commands\MigrationGenerator;
use Zola\CrudGenerator\Console\Commands\ModelGenerator;
use Zola\CrudGenerator\Console\Commands\RepositoryGenerator;
use Zola\CrudGenerator\Console\Commands\ServiceGenerator;

/**
 * Service provider for the CRUD generator package.
 *
 * Registers the package config and the CrudGenerator singleton, and wires the
 * make-model / make-repository / make-service Artisan commands when running in
 * the console.
 */
class CrudGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register package services and configuration.
     *
     * @return void
     */
    public function register(): void
    {
        // Merge package config so the host app always has defaults.
        // The config key MUST match the file name ('crud-generator') so that
        // config('crud-generator.*') resolves in the host app.
        $this->mergeConfigFrom(__DIR__ . '/../config/crud-generator.php', 'crud-generator');

        // Bind the shared helper as a singleton. Note: the imported CrudGenerator
        // in this file is the console command, so the helper must be referenced by
        // its fully-qualified name here, otherwise every resolve returns a fresh
        // instance and per-run state (such as the generated-file registry) is lost.
        $this->app->singleton(
            \Zola\CrudGenerator\CrudGenerator::class,
            fn () => new \Zola\CrudGenerator\CrudGenerator()
        );
    }

    /**
     * Bootstrap config publishing and register console commands.
     *
     * @return void
     */
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
            $this->commands([
                ModelGenerator::class,
                RepositoryGenerator::class,
                ServiceGenerator::class,
                CrudGenerator::class,
                ControllerGenerator::class,
                MigrationGenerator::class,
                DataGenerator::class,
            ]);
        }
    }
}
