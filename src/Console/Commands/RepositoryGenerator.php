<?php

namespace Zola\CrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Zola\CrudGenerator\CrudGenerator;
use Zola\CrudGenerator\Enums\GeneratorType;

/**
 * Console command that generates a repository (and its model, if missing).
 */
class RepositoryGenerator extends Command
{
    protected $signature = 'zola:make-repository
    {repoName : Name of repository}
    {modelName? : If empty, system will create the model if not exists. Model name will be refer to repoName}
    {moduleName? : Module name if you using laravel module}';

    protected $description = 'Create new repository based on model';

    /**
     * Resolve the model name the repository should bind to.
     *
     * @param  string       $repoName   The repository name argument.
     * @param  string|null  $modelName  The explicit model name, when provided.
     * @return string The studly-cased model name.
     */
    protected function resolveModelName(string $repoName, ?string $modelName): string
    {
        return !$modelName ? ucfirst($repoName) : ucfirst($modelName);
    }

    /**
     * Execute the console command.
     *
     * @return int The command exit code (self::SUCCESS or self::FAILURE).
     */
    public function handle(): int
    {
        $repo = $this->argument('repoName');
        $modelName = $this->argument('modelName');
        $moduleName = $this->argument('moduleName');

        $mainService = app(CrudGenerator::class);

        $fixModelName = $this->resolveModelName($repo, $modelName);

        if (!$mainService->checkClassExistance(GeneratorType::Model, $fixModelName, $moduleName)) {
            // Create model first
            $mainService->createModelFromCommand($fixModelName, $moduleName);
        }

        // continue to create repository
        $namespace = $mainService->getNamespace(GeneratorType::Repository, $moduleName);
        $baseRepoClass = "Zola\\CrudGenerator\\Repositories\\BaseRepository";
        $modelClass = $mainService->getModelClassName($fixModelName, $moduleName);

        // Define file name
        $filename = Str::contains($repo, 'Repository') ? $repo : "{$repo}Repository";

        // Setup template
        $stub = file_get_contents($mainService->packagePath('stubs/ZolaRepository.stub'));
        $replacer = str_replace(
            ["{{NAMESPACE}}", "{{BASEREPOSITORYCLASS}}", "{{MODELCLASS}}", "{{MODELNAME}}", "{{CLASSNAME}}"],
            [$namespace, $baseRepoClass, $modelClass, $fixModelName, $filename],
            $stub
        );

        // put file to target dir
        $dir = $mainService->checkDir(GeneratorType::Repository, $moduleName);

        if (! $mainService->writeGeneratedFile("{$dir}/{$filename}.php", $replacer)) {
            $this->error('Failed to create Repository');

            return self::FAILURE;
        }

        $this->info('Success create repository');

        return self::SUCCESS;
    }
}
