<?php

namespace Zola\CrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Zola\CrudGenerator\CrudGenerator;
use Zola\CrudGenerator\Enums\GeneratorType;

class ModelGenerator extends Command
{
    protected $signature = 'zola:make-model {model} {moduleName?}';

    protected $description = "Create model for zola crud generator";

    public function handle(): int
    {
        $name = $this->argument('model');
        $moduleName = $this->argument('moduleName');

        if (config('crud-generator.is_laravel_module') && !$moduleName) {
            $this->error('Module name is required when you define or use laravel module');

            return self::FAILURE;
        }

        if ($name && (Str::contains($name, '-') || Str::contains($name, ' '))) {
            $this->error('Only camel case allowed for model name');

            return self::FAILURE;
        }

        $mainService = new CrudGenerator();

        $namespace = $mainService->getNamespace(GeneratorType::Model, $moduleName ?? null);

        $stub = file_get_contents($mainService->packagePath('stubs/ZolaModel.stub'));
        $replacer = str_replace(
            ['{{NAMESPACE}}', '{{CLASSNAME}}'],
            [$namespace, $name],
            $stub
        );

        // put file to target dir
        $dir = $mainService->checkDir(GeneratorType::Model, $moduleName);

        try {
            file_put_contents("{$dir}/{$name}.php", $replacer);
        } catch (\Throwable $th) {
            $this->error('Failed to create Model');

            return self::FAILURE;
        }

        $this->info('Success create model');

        return self::SUCCESS;
    }
}
