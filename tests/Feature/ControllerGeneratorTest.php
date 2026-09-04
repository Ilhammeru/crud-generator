<?php

use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Controller generator command tests
|--------------------------------------------------------------------------
|
| `zola:make-controller` is the top-level command: it generates the controller
| and, when missing, the service, repository and model it depends on. Like the
| other generator tests, each case runs inside its own throwaway working
| directory because the commands write to paths relative to the CWD.
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

it('generates the controller together with its service, repository and model', function () {
    $exit = Artisan::call('zola:make-controller', [
        'name'  => 'Testing',
        'model' => 'Testing',
    ]);

    $controllerPath = "{$this->workdir}/app/Http/Controllers/TestingController.php";
    $servicePath    = "{$this->workdir}/app/Services/TestingService.php";
    $repoPath       = "{$this->workdir}/app/Repositories/TestingRepository.php";
    $modelPath      = "{$this->workdir}/app/Models/Testing.php";

    // Expected filenames — and NOT the double-extension the bug produced.
    expect($exit)->toBe(0)
        ->and(is_file($controllerPath))->toBeTrue()
        ->and(is_file($servicePath))->toBeTrue()
        ->and(is_file($repoPath))->toBeTrue()
        ->and(is_file($modelPath))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Http/Controllers/TestingController.php.php"))->toBeFalse();

    // Controller: App\Http\Controllers, wired to the service.
    expect(file_get_contents($controllerPath))
        ->toContain('namespace App\\Http\\Controllers;')
        ->toContain('class TestingController extends Controller')
        ->toContain('use App\\Services\\TestingService;')
        ->toContain('private readonly TestingService $service');

    // Service: App\Services, wired to the repository.
    expect(file_get_contents($servicePath))
        ->toContain('namespace App\\Services;')
        ->toContain('class TestingService')
        ->toContain('TestingRepository $repo');

    // Repository: App\Repositories.
    expect(file_get_contents($repoPath))
        ->toContain('namespace App\\Repositories;')
        ->toContain('class TestingRepository extends BaseRepository');
});

it('places every class under the module namespace in module mode', function () {
    config()->set('crud-generator.is_laravel_module', true);

    $exit = Artisan::call('zola:make-controller', [
        'name'       => 'Testing',
        'model'      => 'Testing',
        'moduleName' => 'Blog',
    ]);

    $controllerPath = "{$this->workdir}/Modules/Blog/app/Http/Controllers/TestingController.php";
    $servicePath    = "{$this->workdir}/Modules/Blog/app/Services/TestingService.php";
    $repoPath       = "{$this->workdir}/Modules/Blog/app/Repositories/TestingRepository.php";

    expect($exit)->toBe(0)
        ->and(is_file($controllerPath))->toBeTrue()
        ->and(is_file($servicePath))->toBeTrue()
        ->and(is_file($repoPath))->toBeTrue();

    expect(file_get_contents($controllerPath))
        ->toContain('namespace Modules\\Blog\\Http\\Controllers;')
        ->toContain('use Modules\\Blog\\Services\\TestingService;')
        ->toContain('class TestingController extends Controller');

    expect(file_get_contents($servicePath))
        ->toContain('namespace Modules\\Blog\\Services;')
        ->toContain('class TestingService');

    expect(file_get_contents($repoPath))
        ->toContain('namespace Modules\\Blog\\Repositories;')
        ->toContain('class TestingRepository extends BaseRepository');
});

it('fails when module mode is enabled but no module name is provided', function () {
    config()->set('crud-generator.is_laravel_module', true);

    $exit = Artisan::call('zola:make-controller', [
        'name'  => 'Testing',
        'model' => 'Testing',
    ]);

    // It bails before generating anything, so no module tree is created.
    expect($exit)->toBe(1)
        ->and(is_dir("{$this->workdir}/Modules"))->toBeFalse();
});
