<?php

namespace Zola\CrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Zola\CrudGenerator\CrudGenerator;
use Zola\CrudGenerator\Enums\GeneratorType;

class ServiceGenerator extends Command
{
    protected $signature = 'zola:make-service
    {serviceName : Represent service name}
    {moduleName? : Define module name if using laravel module}
    {--model= : Define existing model. If not exists system will auto create the model and repository}
    {--without-repository= : Leave it if you need to generate repository automatically}';

    protected $description = "Create new service file";

    protected function mainService()
    {
        return app(CrudGenerator::class);
    }

    public function handle(): int
    {
        $name = $this->argument('serviceName');
        $moduleName = $this->argument('moduleName');
        $model = $this->option('model');
        $withRepository = $this->option('without-repository') ?? true; // true will create repository if not exists

        // Handle model
        $modelName = $this->checkModel($name, $model, $moduleName);

        // Handle repository
        [$repositoryNamespace, $repositoryName] = $this->checkRepository($withRepository, $name, $modelName, $moduleName);

        // Create service
        $namespace = $this->mainService()->getNamespace(GeneratorType::Service, $moduleName);
        $filename = Str::contains($name, 'Service') ? $name : "{$name}Service";

        $stub = file_get_contents($this->mainService()->packagePath('stubs/ZolaService.stub'));
        $replacer = str_replace(
            ["{{NAMESPACE}}", "{{REPOSITORYNAMESPACE}}", "{{SERVICENAME}}", "{{REPOSITORYNAME}}"],
            [$namespace, $repositoryNamespace . "\\{$repositoryName}", $filename, $repositoryName],
            $stub
        );

        // put file to target dir
        $dir = $this->mainService()->checkDir(GeneratorType::Service, $moduleName);

        try {
            file_put_contents("{$dir}/{$filename}.php", $replacer);
        } catch (\Throwable $th) {
            $this->error('Failed to create Service');

            return self::FAILURE;
        }

        $this->info('Success create service');

        return self::SUCCESS;
    }

    protected function resolveModelName(string $serviceName, ?string $modelName): string
    {
        return $modelName ? $modelName : $serviceName;
    }

    protected function checkModel(string $serviceName, ?string $modelName, ?string $moduleName): string
    {
        $fixModelName = $this->resolveModelName($serviceName, $modelName);

        if (! $this->mainService()->checkClassExistance(GeneratorType::Model, $fixModelName)) {
            // Create model
            $this->mainService()->createModelFromCommand($fixModelName, $moduleName);
        }

        return $fixModelName;
    }

    protected function checkRepository(bool $isWithRepo, string $serviceName, string $modelName, ?string $moduleName)
    {
        $fixModelName = $this->resolveModelName($serviceName, $modelName) . "Repository";

        if ($isWithRepo && ! $this->mainService()->checkClassExistance(GeneratorType::Model, $fixModelName)) {
            // Create repository
            $this->mainService()->crateRepositoryFromCommand($fixModelName, $modelName, $moduleName);
        }

        $repoNamespace = $this->mainService()->getNamespace(GeneratorType::Repository, $moduleName);

        return [$repoNamespace, $fixModelName];
    }
}
