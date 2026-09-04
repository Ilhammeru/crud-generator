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
        // Controllers live under Http\Controllers to mirror the app/Http/Controllers directory.
        $segment = $type === GeneratorType::Controller ? 'Http\\Controllers' : $type->value;
        return $this->isModuleEnabled() ? "Modules\\{$moduleName}\\{$segment}" : "App\\{$segment}";
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
        $lastPath = $type == GeneratorType::Controller ? 'Http/Controllers' : $type->value;
        return $this->isModuleEnabled() ? "Modules/{$moduleName}/app/{$lastPath}" : "app/{$lastPath}";
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
            // Recursive: in module mode the target sits several levels deep
            // (e.g. "Modules/Blog/app/Repositories") and the parents may not exist yet.
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    /**
     * Determine whether a generated class already exists in the host app.
     *
     * @param  \Zola\CrudGenerator\Enums\GeneratorType  $type        Kind of class to check for.
     * @param  string                                   $name        Short class name, e.g. "Product".
     * @param  string|null                              $moduleName  Module name; used only in module mode.
     * @return bool True when the fully-qualified class is autoloadable.
     */
    public function checkClassExistance(GeneratorType $type, string $name, ?string $moduleName = null): bool
    {
        $namespace = $this->getNamespace($type, $moduleName);
        return class_exists($namespace . "\\{$name}");
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
     * Normalise a model name into its class/file name.
     *
     * @param  string  $name  Raw model name provided by the caller, e.g. "product".
     * @return string The studly-cased model name, e.g. "Product".
     */
    public function defineModelFilename(string $name): string
    {
        return ucfirst($name);
    }

    public function defineControllerFilename(string $name): string
    {
        return \Illuminate\Support\Str::endsWith(strtolower($name), 'controller') ? ucfirst($name) : ucfirst($name) . 'Controller';
    }

    /**
     * Normalise a service name into its class/file name.
     *
     * Appends a "Service" suffix unless one is already present (case-insensitive),
     * so both "Product" and "ProductService" resolve to "ProductService".
     *
     * @param  string  $name  Raw service name provided by the caller.
     * @return string The studly-cased service class name, always ending in "Service".
     */
    public function defineServiceFilename(string $name): string
    {
        $output = \Illuminate\Support\Str::endsWith(strtolower($name), 'service') ? ucfirst($name) : ucfirst($name) . 'Service';

        // 'controller', 'model', 'repository' is forbidden here
        if (\Illuminate\Support\Str::contains($name, 'Controller') || \Illuminate\Support\Str::contains($name, 'controller')) $output = str_replace(['controller', 'Controller'], '', $output);
        if (\Illuminate\Support\Str::contains($name, 'Respository') || \Illuminate\Support\Str::contains($name, 'repository')) $output = str_replace(['Repository', 'repository'], '', $output);

        return $output;
    }

    /**
     * Normalise a repository name into its class/file name.
     *
     * Appends a "Repository" suffix unless one is already present (case-insensitive),
     * so both "Product" and "ProductRepository" resolve to "ProductRepository".
     *
     * @param  string  $name  Raw repository name provided by the caller.
     * @return string The studly-cased repository class name, always ending in "Repository".
     */
    public function defineRepositoryFilename(string $name): string
    {
        $output = \Illuminate\Support\Str::endsWith(strtolower($name), 'repository') ? ucfirst($name) : ucfirst($name) . 'Repository';

        // If output contain 'service' word, remove it. 'service' or 'controller' is forbidden in repository name
        if (\Illuminate\Support\Str::contains($output, 'service') || \Illuminate\Support\Str::contains($output, 'Service')) $output = str_replace(['service', 'Service'], '', $output);
        if (\Illuminate\Support\Str::contains($output, 'controller') || \Illuminate\Support\Str::contains($output, 'Controller')) $output = str_replace(['controller', 'Controller'], '', $output);

        return $output;
    }

    /**
     * Ensure a model exists, generating it from the make-model command when missing.
     *
     * @param  string       $name        Raw model name to resolve and, if needed, create.
     * @param  string|null  $moduleName  Module name; used only in module mode.
     * @return array{0:string,1:string} A [modelNamespace, modelName] pair for the resolved model.
     */
    public function createModelIfNotExists(string $name, ?string $moduleName): array
    {
        $modelName = $this->defineModelFilename($name);

        if (! $this->checkClassExistance(GeneratorType::Model, $modelName, $moduleName)) {
            // Create model
            $this->createModelFromCommand($modelName, $moduleName);
        }

        $modelNamespace = $this->getNamespace(GeneratorType::Model, $moduleName);

        return [$modelNamespace, $modelName];
    }

    /**
     * Ensure a repository exists, generating it from the make-repository command when missing.
     *
     * @param  string       $name        Raw repository name to resolve and, if needed, create.
     * @param  string       $modelName   Model name the repository binds to.
     * @param  string|null  $moduleName  Module name; used only in module mode.
     * @return array{0:string,1:string} A [repositoryNamespace, repositoryName] pair for the resolved repository.
     */
    public function createRepositoryIfNotExists(bool $doCreateRepo, string $name, string $modelName, ?string $moduleName): array
    {
        $repositoryName = $this->defineRepositoryFilename($name);

        if ($doCreateRepo && ! $this->checkClassExistance(GeneratorType::Repository, $repositoryName, $moduleName)) {
            // Create repository
            $this->crateRepositoryFromCommand($repositoryName, $modelName, $moduleName);
        }

        $repoNamespace = $this->getNamespace(GeneratorType::Repository, $moduleName);

        return [$repoNamespace, $repositoryName];
    }

    public function createServiceIfNotExists(string $name, ?string $modelName, ?string $moduleName): array
    {
        $serviceName = $this->defineServiceFilename($name);

        if (! $this->checkClassExistance(GeneratorType::Service, $serviceName, $moduleName)) {
            // Create service
            $this->createServiceFromCommand($serviceName, $modelName, $moduleName);
        }

        $serviceNamespace = $this->getNamespace(GeneratorType::Service, $moduleName) . "\\{$serviceName}";

        return [$serviceNamespace, $serviceName];
    }

    public function createServiceFromCommand(string $serviceName, ?string $modelName, ?string $moduleName): void
    {
        $createService = "zola:make-service {$serviceName}";
        if ($moduleName) $createService .= " {$moduleName}";
        if ($modelName) $createService .= " --model={$modelName}";
        Artisan::call($createService);
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
        // Idempotent: $name may already end in "Repository", so normalise instead
        // of blindly appending (which produced "…RepositoryRepository").
        $repoName = $this->defineRepositoryFilename($name);

        return [$namespace, $repoName];
    }
}
