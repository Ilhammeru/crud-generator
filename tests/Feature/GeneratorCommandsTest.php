<?php

use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Generator command tests
|--------------------------------------------------------------------------
|
| The commands write to paths relative to the *current working directory*
| (e.g. "app/Models/Foo.php"), so every test runs inside its own throwaway
| directory and cleans up afterwards. `app/` is pre-created because the
| package's checkDir() calls mkdir() non-recursively.
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

it('generates a model file', function () {
    $exit = Artisan::call('zola:make-model', ['model' => 'Product']);

    $path = "{$this->workdir}/app/Models/Product.php";

    expect($exit)->toBe(0)
        ->and(is_file($path))->toBeTrue();

    expect(file_get_contents($path))
        ->toContain('namespace App\\Models;')
        ->toContain('class Product extends Model');
});

it('generates a repository together with its model', function () {
    Artisan::call('zola:make-repository', ['repoName' => 'Product']);

    $modelPath = "{$this->workdir}/app/Models/Product.php";
    $repoPath  = "{$this->workdir}/app/Repositories/ProductRepository.php";

    expect(is_file($modelPath))->toBeTrue()
        ->and(is_file($repoPath))->toBeTrue();

    expect(file_get_contents($repoPath))
        ->toContain('namespace App\\Repositories;')
        ->toContain('use Zola\\CrudGenerator\\Repositories\\BaseRepository;')
        ->toContain('use App\\Models\\Product;')
        ->toContain('class ProductRepository extends BaseRepository');
});

it('generates a service (with its repository and model)', function () {
    Artisan::call('zola:make-service', ['serviceName' => 'Product']);

    $servicePath = "{$this->workdir}/app/Services/ProductService.php";

    expect(is_file($servicePath))->toBeTrue();

    expect(file_get_contents($servicePath))
        ->toContain('namespace App\\Services;')
        ->toContain('class ProductService')
        ->toContain('ProductRepository $repo');
});

it('does not append a duplicate "Repository" suffix', function () {
    Artisan::call('zola:make-repository', ['repoName' => 'ProductRepository']);

    expect(is_file("{$this->workdir}/app/Repositories/ProductRepository.php"))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Repositories/ProductRepositoryRepository.php"))->toBeFalse();
});
