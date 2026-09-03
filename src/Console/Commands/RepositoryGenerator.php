<?php

namespace Zola\CrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Zola\CrudGenerator\CrudGenerator;
use Zola\CrudGenerator\Enums\GeneratorType;

class RepositoryGenerator extends Command
{
    protected $signature = 'zola:make-repository
    {repoName : Name of repository}
    {modelName? : If empty, system will create the model if not exists. Model name will be refer to repoName}
    {moduleName?} : Module name if you using laravel module';

    protected $description = 'Create new repository based on model';

    protected function resolveModelName(string $repoName, ?string $modelName): string
    {
        return !$modelName ? ucfirst($repoName) : ucfirst($modelName);
    }

    public function handle()
    {
        $repo = $this->argument('repoName');
        $modelName = $this->argument('modelName');
        $moduleName = $this->argument('moduleName');

        $mainService = new CrudGenerator();

        $fixModelName = $this->resolveModelName($repo, $modelName);

        if (!$mainService->checkClassExistance(GeneratorType::Model, $fixModelName)) {
            // Create model first
            $mainService->createModelFromCommand($fixModelName, $moduleName);
        }

        // continue to create repository
        $namespace = $mainService->getNamespace(GeneratorType::Repository, $moduleName);
        $baseRepoClass = "Zola\\CrudGenerator\\Repositories\\BaseRepository";
        $modelClass = $mainService->getModelClassName($fixModelName, $moduleName);

        // Define file name
        $filename = \Illuminate\Support\Str::contains($repo, 'Repository') ? $repo : "{$repo}Repository";

        // Setup template
        $stub = file_get_contents($mainService->packagePath('stubs/ZolaRepository.stub'));
        $replacer = str_replace(
            ["{{NAMESPACE}}", "{{BASEREPOSITORYCLASS}}", "{{MODELCLASS}}", "{{MODELNAME}}", "{{CLASSNAME}}"],
            [$namespace, $baseRepoClass, $modelClass, $fixModelName, $filename],
            $stub
        );

        // put file to target dir
        $dir = $mainService->checkDir(GeneratorType::Repository, $moduleName);

        try {
            file_put_contents("{$dir}/{$filename}.php", $replacer);
        } catch (\Throwable $th) {
            $this->error('Failed to create Repository');
            exit();
        }

        $this->info('Success create repository');
    }
}
