<?php

namespace Zola\CrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Zola\CrudGenerator\CrudGenerator;
use Zola\CrudGenerator\Support\Fields;

/**
 * Console command that generates a timestamped migration for a model, with one
 * column per supplied field.
 */
class MigrationGenerator extends Command
{
    protected $signature = 'zola:make-migration
    {model : Model name the table is for, e.g. Product}
    {moduleName? : Module name if Laravel Modules mode is enabled}
    {--fields= : Compact field list, e.g. "name:string, price:decimal:nullable"}';

    protected $description = 'Create a database migration for the given model';

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

        $table = Fields::tableName($model);
        $fields = Fields::parse($this->option('fields'));

        $columns = [];
        foreach ($fields as $field) {
            $method = Fields::blueprint($field['type']);
            $args = $field['type'] === 'decimal'
                ? "'{$field['name']}', 8, 2"
                : "'{$field['name']}'";

            $line = "            \$table->{$method}({$args})";
            if ($field['nullable']) {
                $line .= '->nullable()';
            }
            $columns[] = $line . ';';
        }

        $stub = file_get_contents($generator->packagePath('stubs/ZolaMigration.stub'));
        $replacer = str_replace(
            ['{{TABLE}}', '{{COLUMNS}}'],
            [$table, implode("\n", $columns)],
            $stub
        );

        $dir = $generator->migrationDir($moduleName);
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $filename = date('Y_m_d_His') . "_create_{$table}_table.php";

        if (! $generator->writeGeneratedFile("{$dir}/{$filename}", $replacer)) {
            $this->error('Failed to create migration');

            return self::FAILURE;
        }

        $this->info('Success create migration');

        return self::SUCCESS;
    }
}
