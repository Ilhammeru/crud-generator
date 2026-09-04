<?php

namespace Zola\CrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Zola\CrudGenerator\CrudGenerator;
use Zola\CrudGenerator\Enums\GeneratorType;

class ControllerGenerator extends Command
{
    protected $signature = 'zola:make-controller
    {name : Controller name}
    {model : Model name. System will create a model if not exists}
    {moduleName? : Module name if laravel module installed and active}
    {service? : Service name. System will create a service if not exists. If not provieed, service name will be taken from Model name}';

    protected $description = 'Command to create base controller for crud process';

    protected CrudGenerator $service;

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

    public function handle(): int
    {
        $name = $this->argument('name');
        $model = $this->argument('model');
        $service = $this->argument('service');
        $moduleName = $this->argument('moduleName');

        $this->service = app(CrudGenerator::class);

        if ($this->service->isModuleEnabled() && ! $moduleName) {
            $this->error('Module name is required when you define or use laravel module');

            return self::FAILURE;
        }

        $controllerName = $this->service->defineControllerFilename($name);
        $controllerNamespace = $this->service->getNamespace(GeneratorType::Controller, $moduleName);
        $filename = $controllerName;

        // Create model
        [$modelNamespace, $modelName] = $this->service->createModelIfNotExists($this->resolveModelName($name, $model), $moduleName);

        [$serviceNamespace, $serviceName] = $this->service->createServiceIfNotExists($service ?? $name, $modelName, $moduleName);

        $stub = file_get_contents($this->service->packagePath('stubs/ZolaController.stub'));
        $replacer = str_replace(
            ["{{NAMESPACE}}", "{{SERVICENAMESPACE}}", "{{CLASSNAME}}", "{{SERVICENAME}}"],
            [$controllerNamespace, $serviceNamespace, $controllerName, $serviceName],
            $stub
        );

        // put file to target dir
        $dir = $this->service->checkDir(GeneratorType::Controller, $moduleName);

        try {
            file_put_contents("{$dir}/{$filename}.php", $replacer);
        } catch (\Throwable $th) {
            $this->error('Failed to create controller');

            return self::FAILURE;
        }

        $this->info('Success create controller');

        return self::SUCCESS;
    }
}
