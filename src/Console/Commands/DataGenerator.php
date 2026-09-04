<?php

namespace Zola\CrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Zola\CrudGenerator\CrudGenerator;
use Zola\CrudGenerator\Enums\GeneratorType;
use Zola\CrudGenerator\Support\Fields;

/**
 * Console command that generates spatie/laravel-data Data classes for a model.
 *
 * Two variants are produced by default: a Store{Model}Data used for creation
 * (fields keep their required/nullable rules) and an Update{Model}Data used for
 * partial updates (every field becomes optional via the Sometimes rule).
 */
class DataGenerator extends Command
{
    protected $signature = 'zola:make-data
    {model : Model name the Data classes are for, e.g. Product}
    {moduleName? : Module name if Laravel Modules mode is enabled}
    {--fields= : Compact field list, e.g. "name:string, price:decimal:nullable"}
    {--variant=* : Which variants to generate: store, update. Defaults to both}';

    protected $description = 'Create laravel-data Data classes (store/update) for the given model';

    /**
     * Execute the console command.
     *
     * @return int The command exit code (self::SUCCESS or self::FAILURE).
     */
    public function handle(): int
    {
        $model = $this->argument('model');
        $moduleName = $this->argument('moduleName');

        $generator = app(CrudGenerator::class);

        if ($generator->isModuleEnabled() && ! $moduleName) {
            $this->error('Module name is required when Laravel Modules mode is enabled');

            return self::FAILURE;
        }

        $fields = Fields::parse($this->option('fields'));
        $variants = $this->option('variant') ?: ['store', 'update'];

        $namespace = $generator->getNamespace(GeneratorType::Data, $moduleName);
        $dir = $generator->checkDir(GeneratorType::Data, $moduleName);
        $stub = file_get_contents($generator->packagePath('stubs/ZolaData.stub'));

        foreach ($variants as $variant) {
            $className = ucfirst($variant) . $model . 'Data';
            [$uses, $properties] = $this->buildBody($fields, $variant === 'update');

            $replacer = str_replace(
                ['{{NAMESPACE}}', '{{CLASSNAME}}', '{{USE_ATTRIBUTES}}', '{{PROPERTIES}}'],
                [$namespace, $className, $uses, $properties],
                $stub
            );

            if (! $generator->writeGeneratedFile("{$dir}/{$className}.php", $replacer)) {
                $this->error("Failed to create {$className}");

                return self::FAILURE;
            }
        }

        $this->info('Success create data');

        return self::SUCCESS;
    }

    /**
     * Build the validation-attribute imports and promoted constructor properties.
     *
     * @param  array<int, array{name:string, type:string, nullable:bool}>  $fields
     * @param  bool  $isUpdate  When true, every field is optional (Sometimes + nullable).
     * @return array{0:string, 1:string} A [useLines, propertyLines] pair.
     */
    protected function buildBody(array $fields, bool $isUpdate): array
    {
        $attributeClasses = [];
        $required = [];
        $optional = [];

        foreach ($fields as $field) {
            $rules = Fields::validation($field);

            if ($isUpdate) {
                // Partial update: drop presence rules and mark the field optional.
                $rules = array_values(array_filter(
                    $rules,
                    fn ($rule) => $rule !== 'Required' && $rule !== 'Nullable'
                ));
                array_unshift($rules, 'Sometimes');
            }

            foreach ($rules as $rule) {
                $attributeClasses[] = explode('(', $rule)[0];
            }

            $nullable = $isUpdate || $field['nullable'];
            $typeHint = ($nullable ? '?' : '') . Fields::phpType($field['type']);
            $attributes = implode(', ', $rules);
            $property = "        #[{$attributes}]\n        public {$typeHint} \${$field['name']}" . ($nullable ? ' = null' : '') . ',';

            // A promoted property with a default must not precede one without a
            // default, so nullable fields are emitted after the required ones.
            $nullable ? $optional[] = $property : $required[] = $property;
        }

        $attributeClasses = array_values(array_unique($attributeClasses));
        sort($attributeClasses);

        $uses = $attributeClasses === []
            ? ''
            : implode("\n", array_map(
                fn ($class) => "use Spatie\\LaravelData\\Attributes\\Validation\\{$class};",
                $attributeClasses
            )) . "\n";

        return [$uses, implode("\n", array_merge($required, $optional))];
    }
}
