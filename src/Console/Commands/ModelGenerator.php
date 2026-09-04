<?php

namespace Zola\CrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Zola\CrudGenerator\CrudGenerator;
use Zola\CrudGenerator\Enums\GeneratorType;
use Zola\CrudGenerator\Support\Fields;

/**
 * Console command that generates an Eloquent model from the package stub.
 */
class ModelGenerator extends Command
{
    protected $signature = 'zola:make-model {model} {moduleName?} {--fields= : Compact field list, e.g. "name:string, price:decimal:nullable"}';

    protected $description = "Create model for zola crud generator";

    /**
     * Execute the console command.
     *
     * @return int The command exit code (self::SUCCESS or self::FAILURE).
     */
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

        $mainService = app(CrudGenerator::class);

        $namespace = $mainService->getNamespace(GeneratorType::Model, $moduleName ?? null);

        $fields = Fields::parse($this->option('fields'));

        $fillableItems = array_map(fn ($f) => "'{$f['name']}'", $fields);

        $castItems = [];
        foreach ($fields as $field) {
            $cast = Fields::cast($field['type']);
            if ($cast !== null) {
                $castItems[] = "'{$field['name']}' => '{$cast}'";
            }
        }

        $stub = file_get_contents($mainService->packagePath('stubs/ZolaModel.stub'));
        $replacer = str_replace(
            ['{{NAMESPACE}}', '{{CLASSNAME}}', '{{FILLABLE}}', '{{CASTS}}'],
            [$namespace, $name, Fields::renderList($fillableItems), Fields::renderList($castItems)],
            $stub
        );

        // put file to target dir
        $dir = $mainService->checkDir(GeneratorType::Model, $moduleName);

        if (! $mainService->writeGeneratedFile("{$dir}/{$name}.php", $replacer)) {
            $this->error('Failed to create Model');

            return self::FAILURE;
        }

        $this->info('Success create model');

        return self::SUCCESS;
    }
}
