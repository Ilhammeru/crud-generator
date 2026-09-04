<?php

namespace Zola\CrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Zola\CrudGenerator\CrudGenerator;
use Zola\CrudGenerator\Enums\GeneratorType;

/**
 * Console command that generates a service, together with its repository and
 * model when those do not already exist.
 */
class ServiceGenerator extends Command
{
    private CrudGenerator $service;

    protected $signature = 'zola:make-service
    {serviceName : Represent service name}
    {moduleName? : Define module name if using laravel module}
    {--model= : Define existing model. If not exists system will auto create the model and repository}
    {--without-repository= : Leave it if you need to generate repository automatically}';

    protected $description = "Create new service file";

    /**
     * Resolve the model name the service should use.
     *
     * @param  string       $serviceName  The service name argument.
     * @param  string|null  $modelName    The explicit model name, when provided.
     * @return string The resolved model name.
     */
    protected function resolveModelName(string $serviceName, ?string $modelName): string
    {
        return $modelName ? $modelName : $serviceName;
    }

    /**
     * Execute the console command.
     *
     * @return int The command exit code (self::SUCCESS or self::FAILURE).
     */
    public function handle(): int
    {
        // Define service
        $this->service = app(CrudGenerator::class);

        $name = $this->argument('serviceName');
        $moduleName = $this->argument('moduleName');
        $model = $this->option('model');
        $withRepository = $this->option('without-repository') ?? true; // true will create repository if not exists

        // Handle model
        [$modelNamespace, $modelName] = $this->service->createModelIfNotExists($this->resolveModelName($name, $model), $moduleName);

        // Handle repository
        [$repositoryNamespace, $repositoryName] = $this->service->createRepositoryIfNotExists($withRepository, $name, $modelName, $moduleName);

        // Create service
        $namespace = $this->service->getNamespace(GeneratorType::Service, $moduleName);
        $filename = Str::contains($name, 'Service') ? $name : "{$name}Service";

        $stub = file_get_contents($this->service->packagePath('stubs/ZolaService.stub'));
        $replacer = str_replace(
            ["{{NAMESPACE}}", "{{REPOSITORYNAMESPACE}}", "{{SERVICENAME}}", "{{REPOSITORYNAME}}"],
            [$namespace, $repositoryNamespace . "\\{$repositoryName}", $filename, $repositoryName],
            $stub
        );

        // put file to target dir
        $dir = $this->service->checkDir(GeneratorType::Service, $moduleName);

        try {
            file_put_contents("{$dir}/{$filename}.php", $replacer);
        } catch (\Throwable $th) {
            $this->error('Failed to create Service');

            return self::FAILURE;
        }

        $this->info('Success create service');

        return self::SUCCESS;
    }
}
