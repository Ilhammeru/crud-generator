<?php

namespace Zola\CrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Zola\CrudGenerator\CrudGenerator as ZolaCrudGenerator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class CrudGenerator extends Command
{
    private ZolaCrudGenerator $service;

    protected $signature = 'zola:make-crud {model?} {--fields=} {--only=*}';

    // Auto-prompts only for the missing *required* args, with your wording:
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'model' => ['What model should this CRUD be for?', 'e.g. Product'],
        ];
    }

    public function handle()
    {
        // Define service
        $this->service = app(ZolaCrudGenerator::class);

        $model = $this->argument('model');
        if (! $model) {
            $model = text(
                'Model name',
                placeholder: 'Product',
                validate: fn($v) => preg_match('/^[A-Z][A-Za-z0-9]+$/', $v)
                    ? null : 'Use PascalCase, e.g. Product'
            );
        }

        // Which artifacts to generate — a checklist, all pre-selected
        $parts = multiselect(
            label: 'What should I generate?',
            options: [
                'model' => 'Model',
                'migration' => 'Migration',
                'repository' => 'Repository',
                'service' => 'Service',
                'controller' => 'Controller',
                'data' => 'Data (spatie/laravel-data)'
            ],
            default: ['model', 'migration', 'repository', 'service', 'controller', 'data'],
        );

        // Collect fields in a loop
        $fields = [];
        do {
            $name = text('Field name', placeholder: 'leave empty to finish');
            if ($name === '') break;

            $type = select('Type', ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'foreignId']);
            $nullable = confirm('Nullable?', default: false);

            $fields[] = compact('name', 'type', 'nullable');
        } while (true);

        // Preview before writing — this builds huge trust
        table(
            ['Field', 'Type', 'Nullable'],
            array_map(fn($f) => [$f['name'], $f['type'], $f['nullable'] ? 'yes' : 'no'], $fields)
        );

        if (! confirm("Generate CRUD for {$model} with these fields?")) {
            $this->warn('Aborted.');
            return self::SUCCESS;
        }

        // ... delegate to your arg-driven sub-commands here ...
        $this->info("List of fields is " . json_encode($fields));
        $this->info("\n");
        $this->info("Generation is: " . json_encode($parts));
        return self::SUCCESS;
    }

    protected function createModel(string $name) {}
}
