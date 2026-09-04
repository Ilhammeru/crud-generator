<?php

namespace Zola\CrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Zola\CrudGenerator\CrudGenerator as ZolaCrudGenerator;
use Zola\CrudGenerator\Support\Fields;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

/**
 * Top-level CRUD command.
 *
 * Runs interactively (Laravel Prompts) when no model argument is given, and
 * headless when a model is passed, so it stays scriptable and testable. It
 * delegates file generation to the make-model, make-repository, make-service,
 * make-controller, make-migration and make-data commands.
 */
class CrudGenerator extends Command
{
    private ZolaCrudGenerator $service;

    protected $signature = 'zola:make-crud
    {model? : Model name in PascalCase, e.g. Product. Prompted when omitted}
    {--only=* : Limit generation to these parts: model, migration, repository, service, controller, data}
    {--module= : Module name, required when Laravel Modules mode is enabled}
    {--fields= : Compact field list, e.g. "name:string, price:decimal:nullable"}
    {--data=* : Which Data variants to generate: store, update. Defaults to both}';

    protected $description = 'Generate a full CRUD stack (model, migration, repository, service, controller, data)';

    /**
     * Class layers ordered from highest to lowest. A higher layer generates
     * the layers it depends on, so only the highest selected one is called.
     */
    private const CLASS_LAYERS = ['controller', 'service', 'repository', 'model'];

    /**
     * Execute the console command.
     *
     * @return int The command exit code (self::SUCCESS or self::FAILURE).
     */
    public function handle(): int
    {
        $this->service = app(ZolaCrudGenerator::class);

        $model = $this->argument('model');
        $scripted = $model !== null;

        // Resolve the model name.
        if (! $scripted) {
            $model = text(
                'Model name',
                placeholder: 'Product',
                validate: fn ($v) => $this->validateModelName($v),
            );
        } elseif ($error = $this->validateModelName($model)) {
            $this->error($error);

            return self::FAILURE;
        }

        // Resolve which parts to generate.
        $parts = $scripted
            ? ($this->option('only') ?: ['controller', 'migration', 'data'])
            : multiselect(
                label: 'What should I generate?',
                options: [
                    'model' => 'Model',
                    'migration' => 'Migration',
                    'repository' => 'Repository',
                    'service' => 'Service',
                    'controller' => 'Controller',
                    'data' => 'Data (spatie/laravel-data)',
                ],
                default: ['model', 'migration', 'repository', 'service', 'controller', 'data'],
            );

        // Resolve the module name when running in Laravel Modules mode.
        $moduleName = null;
        if ($this->service->isModuleEnabled()) {
            $moduleName = $scripted
                ? $this->option('module')
                : text('Module name', placeholder: 'Blog', validate: fn ($v) => $v === '' ? 'Module name is required in module mode' : null);

            if (! $moduleName) {
                $this->error('Module name is required when Laravel Modules mode is enabled (pass --module).');

                return self::FAILURE;
            }
        }

        // Resolve fields.
        $fields = $scripted ? Fields::parse($this->option('fields')) : $this->collectFields();

        // Resolve which Data variants to generate.
        $dataVariants = ['store', 'update'];
        if (in_array('data', $parts, true)) {
            $dataVariants = $scripted
                ? ($this->option('data') ?: $dataVariants)
                : multiselect(
                    label: 'Which Data classes should I create?',
                    options: [
                        'store' => "Store{$model}Data (create)",
                        'update' => "Update{$model}Data (partial update)",
                    ],
                    default: $dataVariants,
                );
        }

        // Interactive preview and confirmation.
        if (! $scripted) {
            if ($fields !== []) {
                table(
                    ['Field', 'Type', 'Nullable'],
                    array_map(fn ($f) => [$f['name'], $f['type'], $f['nullable'] ? 'yes' : 'no'], $fields),
                );
            }

            if (! confirm("Generate CRUD for {$model}?")) {
                $this->warn('Aborted.');

                return self::SUCCESS;
            }
        }

        return $this->generate($model, $parts, $moduleName, $fields, $dataVariants);
    }

    /**
     * Delegate generation to the underlying generator commands.
     *
     * @param  string                                                       $model         Resolved model name.
     * @param  array<string>                                                $parts         Selected parts.
     * @param  string|null                                                  $moduleName    Module name, when in module mode.
     * @param  array<int, array{name:string, type:string, nullable:bool}>   $fields        Collected field definitions.
     * @param  array<string>                                                $dataVariants  Data variants to generate.
     * @return int The command exit code.
     */
    protected function generate(string $model, array $parts, ?string $moduleName, array $fields, array $dataVariants): int
    {
        // Track files created this run so a mid-way failure can be rolled back.
        $this->service->resetGeneratedFiles();

        $fieldsArg = Fields::serialize($fields);
        $top = $this->highestSelectedLayer($parts);

        try {
            // 1. Model first, so it carries $fillable and $casts before any cascade runs.
            if ($top !== null) {
                $this->runStep('model', 'zola:make-model', $this->withModule(array_filter(['model' => $model, '--fields' => $fieldsArg]), $moduleName));
            }

            // 2. Data next, with the fields, so the class cascade finds it and does not
            //    scaffold empty replacements. A generated service or controller type-hints
            //    both Data classes, so both variants are forced in that case.
            $servicesData = in_array($top, ['service', 'controller'], true);
            if (in_array('data', $parts, true) || $servicesData) {
                $args = array_filter(['model' => $model, '--fields' => $fieldsArg]);
                $args['--variant'] = $servicesData ? ['store', 'update'] : $dataVariants;
                $this->runStep('data', 'zola:make-data', $this->withModule($args, $moduleName));
            }

            // 3. The rest of the class stack on top of the model.
            if ($top !== null && $top !== 'model') {
                [$command, $args] = match ($top) {
                    'controller' => ['zola:make-controller', $this->withModule(['name' => $model, 'model' => $model], $moduleName)],
                    'service' => ['zola:make-service', $this->withModule(['serviceName' => $model, '--model' => $model], $moduleName)],
                    'repository' => ['zola:make-repository', $this->withModule(['repoName' => $model, 'modelName' => $model], $moduleName)],
                };
                $this->runStep($top, $command, $args);
            }

            // 4. Migration.
            if (in_array('migration', $parts, true)) {
                $this->runStep('migration', 'zola:make-migration', $this->withModule(array_filter(['model' => $model, '--fields' => $fieldsArg]), $moduleName));
            }
        } catch (\Throwable $e) {
            $removed = $this->service->rollbackGeneratedFiles();
            $this->error("Generation failed: {$e->getMessage()}");
            if ($removed !== []) {
                $this->warn('Rolled back ' . count($removed) . ' generated file(s).');
            }

            return self::FAILURE;
        }

        $this->info("CRUD generation for {$model} finished.");

        return self::SUCCESS;
    }

    /**
     * Run one delegated generator command, throwing when it does not succeed so
     * the caller can roll back everything created so far.
     *
     * @param  string                $label    Human label for the part, used in the message.
     * @param  string                $command  Artisan command name to call.
     * @param  array<string, mixed>  $args     Arguments and options for the command.
     * @return void
     */
    protected function runStep(string $label, string $command, array $args): void
    {
        if ($this->call($command, $args) !== self::SUCCESS) {
            throw new \RuntimeException("failed while creating the {$label}");
        }
    }

    /**
     * Return the highest class layer present in the selection, or null when none.
     *
     * @param  array<string>  $parts
     */
    protected function highestSelectedLayer(array $parts): ?string
    {
        foreach (self::CLASS_LAYERS as $layer) {
            if (in_array($layer, $parts, true)) {
                return $layer;
            }
        }

        return null;
    }

    /**
     * Append the module argument when a module name is set.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function withModule(array $args, ?string $moduleName): array
    {
        if ($moduleName) {
            $args['moduleName'] = $moduleName;
        }

        return $args;
    }

    /**
     * Collect field definitions interactively until an empty name is entered.
     *
     * @return array<int, array{name:string, type:string, nullable:bool}>
     */
    protected function collectFields(): array
    {
        $fields = [];

        do {
            $name = text('Field name', placeholder: 'leave empty to finish');
            if ($name === '') {
                break;
            }

            $fields[] = [
                'name' => $name,
                'type' => select('Type', ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'foreignId']),
                'nullable' => confirm('Nullable?', default: false),
            ];
        } while (true);

        return $fields;
    }

    /**
     * Validate a model name. Returns an error message, or null when valid.
     */
    protected function validateModelName(string $value): ?string
    {
        return preg_match('/^[A-Z][A-Za-z0-9]+$/', $value) ? null : 'Use PascalCase, e.g. Product';
    }
}
