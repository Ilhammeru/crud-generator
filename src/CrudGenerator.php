<?php

namespace Zola\CrudGenerator;

use Illuminate\Support\Facades\Artisan;
use Zola\CrudGenerator\Enums\GeneratorType;

class CrudGenerator
{
    public function test()
    {
        return base_path();
    }

    public function packagePath(string $path = ''): string
    {
        return dirname(__DIR__) . ($path != '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }

    public function isModuleEnabled(): bool
    {
        return config('crud-generator.is_laravel_module') ? true : false;
    }

    public function getNamespace(\Zola\CrudGenerator\Enums\GeneratorType $type, ?string $moduleName): string
    {
        return $this->isModuleEnabled() ? "Modules\\{$moduleName}\{$type->value}" : "App\\{$type->value}";
    }

    public function getTargetDir(\Zola\CrudGenerator\Enums\GeneratorType $type, ?string $moduleName): string
    {
        return $this->isModuleEnabled() ? "Modules/{$moduleName}/app/{$type->value}" : "app/{$type->value}";
    }

    public function checkDir(\Zola\CrudGenerator\Enums\GeneratorType $type, ?string $moduleName)
    {
        $dir = $this->getTargetDir($type, $moduleName);
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        return $dir;
    }

    public function checkClassExistance(GeneratorType $type, string $name)
    {
        $namespace = $this->getNamespace($type, $name);
        return class_exists($namespace . "\\{$name}") ? true : false;
    }

    public function getModelClassName(string $modelName, ?string $moduleName): string
    {
        return $this->isModuleEnabled() ? "Modules\\{$moduleName}\\Models\\{$modelName}" : "App\\Models\\{$modelName}";
    }

    public function createModelFromCommand(string $fixModelName, ?string $moduleName): void
    {
        $createModel = "zola:make-model {$fixModelName}";
        if ($moduleName) $createModel .= " {$moduleName}";
        Artisan::call($createModel);
    }

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
