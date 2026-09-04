<?php

use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| make-crud command tests (headless path)
|--------------------------------------------------------------------------
|
| `zola:make-crud` runs interactively when invoked bare, but takes a headless
| path when a model argument is supplied. These tests drive the headless path
| so no prompts are involved. Each case runs in its own throwaway directory.
|
*/

beforeEach(function () {
    config()->set('crud-generator.is_laravel_module', false);

    $this->workdir = sys_get_temp_dir() . '/zola-crud-' . uniqid();
    mkdir($this->workdir . '/app', 0777, true);

    $this->originalCwd = getcwd();
    chdir($this->workdir);
});

afterEach(function () {
    chdir($this->originalCwd);

    $rrmdir = function (string $dir) use (&$rrmdir): void {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = "{$dir}/{$entry}";
            is_dir($path) ? $rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    };

    if (is_dir($this->workdir)) {
        $rrmdir($this->workdir);
    }
});

it('generates the full class stack when --only=controller', function () {
    $exit = Artisan::call('zola:make-crud', ['model' => 'Invoice', '--only' => ['controller']]);

    expect($exit)->toBe(0)
        ->and(is_file("{$this->workdir}/app/Http/Controllers/InvoiceController.php"))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Services/InvoiceService.php"))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Repositories/InvoiceRepository.php"))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Models/Invoice.php"))->toBeTrue();
});

it('defaults to the full stack when --only is omitted', function () {
    $exit = Artisan::call('zola:make-crud', ['model' => 'Order']);

    expect($exit)->toBe(0)
        ->and(is_file("{$this->workdir}/app/Http/Controllers/OrderController.php"))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Services/OrderService.php"))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Repositories/OrderRepository.php"))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Models/Order.php"))->toBeTrue();
});

it('generates only the model when --only=model', function () {
    $exit = Artisan::call('zola:make-crud', ['model' => 'Tag', '--only' => ['model']]);

    expect($exit)->toBe(0)
        ->and(is_file("{$this->workdir}/app/Models/Tag.php"))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Services/TagService.php"))->toBeFalse()
        ->and(is_dir("{$this->workdir}/app/Http"))->toBeFalse();
});

it('rejects a non-PascalCase model name', function () {
    $exit = Artisan::call('zola:make-crud', ['model' => 'my-model']);

    // Bails on validation before anything is generated.
    expect($exit)->toBe(1)
        ->and(is_dir("{$this->workdir}/app/Http"))->toBeFalse()
        ->and(is_dir("{$this->workdir}/app/Models"))->toBeFalse();
});

it('fails in module mode when --module is missing', function () {
    config()->set('crud-generator.is_laravel_module', true);

    $exit = Artisan::call('zola:make-crud', ['model' => 'Invoice', '--only' => ['controller']]);

    expect($exit)->toBe(1)
        ->and(is_dir("{$this->workdir}/Modules"))->toBeFalse();
});

it('rolls back everything it created when a later step fails', function () {
    // Force the migration step (which runs last) to fail by placing a FILE where
    // the migrations directory should be, so the class/data files created earlier
    // in the run must be rolled back.
    mkdir("{$this->workdir}/database", 0777, true);
    file_put_contents("{$this->workdir}/database/migrations", 'not a directory');

    $exit = Artisan::call('zola:make-crud', [
        'model'  => 'Position',
        '--only' => ['controller', 'migration', 'data'],
    ]);

    expect($exit)->toBe(1)
        ->and(is_file("{$this->workdir}/app/Models/Position.php"))->toBeFalse()
        ->and(is_file("{$this->workdir}/app/Services/PositionService.php"))->toBeFalse()
        ->and(is_file("{$this->workdir}/app/Repositories/PositionRepository.php"))->toBeFalse()
        ->and(is_file("{$this->workdir}/app/Http/Controllers/PositionController.php"))->toBeFalse()
        ->and(is_file("{$this->workdir}/app/Data/StorePositionData.php"))->toBeFalse()
        ->and(is_file("{$this->workdir}/app/Data/UpdatePositionData.php"))->toBeFalse();
});

it('generates under the module namespace with --module', function () {
    config()->set('crud-generator.is_laravel_module', true);

    $exit = Artisan::call('zola:make-crud', [
        'model'    => 'Invoice',
        '--only'   => ['controller'],
        '--module' => 'Blog',
    ]);

    $controllerPath = "{$this->workdir}/Modules/Blog/app/Http/Controllers/InvoiceController.php";

    expect($exit)->toBe(0)
        ->and(is_file($controllerPath))->toBeTrue()
        ->and(is_file("{$this->workdir}/Modules/Blog/app/Services/InvoiceService.php"))->toBeTrue();

    expect(file_get_contents($controllerPath))
        ->toContain('namespace Modules\\Blog\\Http\\Controllers;');
});
