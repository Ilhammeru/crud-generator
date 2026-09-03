<?php

namespace Zola\CrudGenerator;

use Illuminate\Support\Facades\Artisan;
use Zola\CrudGenerator\Enums\GeneratorType;

/**
 * Core helper shared by the CRUD generator commands.
 *
 * Centralises path, namespace and directory resolution so the make-model,
 * make-repository and make-service commands stay thin and the "app mode vs.
 * module mode" branching lives in a single place.
 */
class CrudGenerator
{
    /**
     * Smoke-test helper returning the host application base path.
     *
     * @return string The absolute base path of the host Laravel application.
     */
    public function test()
    {
        return base_path();
    }

    /**
     * Resolve an absolute path inside this package.
     *
     * @param  string  $path  Path relative to the package root (e.g. "stubs/ZolaModel.stub").
     *                        Leading slashes are trimmed; pass an empty string for the package root.
     * @return string The absolute filesystem path.
     */
    public function packagePath(string $path = ''): string
    {
        return dirname(__DIR__) . ($path != '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }

    /**
     * Determine whether the package runs in Laravel Modules mode.
     *
     * @return bool True when config('crud-generator.is_laravel_module') is enabled.
     */
    public function isModuleEnabled(): bool
    {
        return config('crud-generator.is_laravel_module') ? true : false;
    }

    /**
     * Build the target namespace for a generated class.
     *
     * @param  \Zola\CrudGenerator\Enums\GeneratorType  $type        Kind of class being generated (Model, Repository, ...).
     * @param  string|null                              $moduleName  Module name; used only in module mode.
     * @return string Fully-qualified namespace, e.g. "App\Models" or "Modules\Blog\Models".
     */
    public function getNamespace(\Zola\CrudGenerator\Enums\GeneratorType $type, ?string $moduleName): string
    {
        return $this->isModuleEnabled() ? "Modules\\{$moduleName}\\{$type->value}" : "App\\{$type->value}";
    }

    /**
     * Build the target directory (relative to the working directory) for a generated class.
     *
     * @param  \Zola\CrudGenerator\Enums\GeneratorType  $type        Kind of class being generated.
     * @param  string|null                              $moduleName  Module name; used only in module mode.
     * @return string Directory relative to the working directory, e.g. "app/Models".
     */
    public function getTargetDir(\Zola\CrudGenerator\Enums\GeneratorType $type, ?string $moduleName): string
    {
        return $this->isModuleEnabled() ? "Modules/{$moduleName}/app/{$type->value}" : "app/{$type->value}";
    }

    /**
     * Resolve the target directory, creating it when it does not yet exist.
     *
     * @param  \Zola\CrudGenerator\Enums\GeneratorType  $type        Kind of class being generated.
     * @param  string|null                              $moduleName  Module name; used only in module mode.
     * @return string The target directory, guaranteed to exist on return.
     */
    public function checkDir(\Zola\CrudGenerator\Enums\GeneratorType $type, ?string $moduleName)
    {
        $dir = $this->getTargetDir($type, $moduleName);
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        return $dir;
    }

    /**
     * Determine whether a generated class already exists in the host app.
     *
     * @param  \Zola\CrudGenerator\Enums\GeneratorType  $type  Kind of class to check for.
     * @param  string                                   $name  Short class name, e.g. "Product".
     * @return bool True when the fully-qualified class is autoloadable.
     */
    public function checkClassExistance(GeneratorType $type, string $name)
    {
        $namespace = $this->getNamespace($type, $name);
        return class_exists($namespace . "\\{$name}") ? true : false;
    }

    /**
     * Build the fully-qualified model class name for the given model.
     *
     * @param  string       $modelName   Short model name, e.g. "Product".
     * @param  string|null  $moduleName  Module name; used only in module mode.
     * @return string Fully-qualified model class, e.g. "App\Models\Product".
     */
    public function getModelClassName(string $modelName, ?string $moduleName): string
    {
        return $this->isModuleEnabled() ? "Modules\\{$moduleName}\\Models\\{$modelName}" : "App\\Models\\{$modelName}";
    }

    /**
     * Generate a model by delegating to the make-model command.
     *
     * @param  string       $fixModelName  Resolved model name to create.
     * @param  string|null  $moduleName    Module name, appended when in module mode.
     * @return void
     */
    public function createModelFromCommand(string $fixModelName, ?string $moduleName): void
    {
        $createModel = "zola:make-model {$fixModelName}";
        if ($moduleName) $createModel .= " {$moduleName}";
        Artisan::call($createModel);
    }

    /**
     * Generate a repository by delegating to the make-repository command.
     *
     * @param  string       $name        Repository base name to create.
     * @param  string       $modelName   Model name the repository binds to.
     * @param  string|null  $moduleName  Module name, appended when in module mode.
     * @return array{0:string,1:string} A [namespace, repositoryName] pair for the created repository.
     */
    public function crateRepositoryFromCommand(string $name, string $modelName, ?string $moduleName): array
    {
        $createRepo = "zola:make-repository {$name} {$modelName}";
        if ($moduleName) $createRepo .= " {$moduleName}";
        Artisan::call($createRepo);

        $namespace = $this->getNamespace(GeneratorType::Repository, $moduleName);
        $repoName = "{$name}Repository";

        return [$namespace, $repoName];
    }
}
