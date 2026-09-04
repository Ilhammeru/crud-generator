<?php

use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Field-driven generation tests
|--------------------------------------------------------------------------
|
| Covers the parts that consume the collected fields: the model ($fillable and
| $casts), the migration (columns) and the laravel-data Data classes. Each case
| runs in its own throwaway working directory.
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

it('populates the model $fillable and $casts from fields', function () {
    Artisan::call('zola:make-model', [
        'model'    => 'Product',
        '--fields' => 'title:string, body:text:nullable, views:integer',
    ]);

    expect(file_get_contents("{$this->workdir}/app/Models/Product.php"))
        ->toContain("'title'")
        ->toContain("'body'")
        ->toContain("'views'")
        ->toContain("'views' => 'integer'");
});

it('generates a timestamped migration with columns', function () {
    Artisan::call('zola:make-migration', [
        'model'    => 'Product',
        '--fields' => 'title:string, price:decimal:nullable',
    ]);

    $matches = glob("{$this->workdir}/database/migrations/*_create_products_table.php");

    expect($matches)->toHaveCount(1);

    expect(file_get_contents($matches[0]))
        ->toContain("Schema::create('products'")
        ->toContain("\$table->string('title');")
        ->toContain("\$table->decimal('price', 8, 2)->nullable();");
});

it('generates store and update Data classes with derived rules', function () {
    Artisan::call('zola:make-data', [
        'model'    => 'Product',
        '--fields' => 'title:string, views:integer:nullable',
    ]);

    $store = "{$this->workdir}/app/Data/StoreProductData.php";
    $update = "{$this->workdir}/app/Data/UpdateProductData.php";

    expect(is_file($store))->toBeTrue()
        ->and(is_file($update))->toBeTrue();

    expect(file_get_contents($store))
        ->toContain('namespace App\\Data;')
        ->toContain('class StoreProductData extends Data')
        ->toContain('use Spatie\\LaravelData\\Attributes\\Validation\\Required;')
        ->toContain('#[Required, StringType, Max(255)]')
        ->toContain('public string $title')
        ->toContain('#[Nullable, IntegerType]')
        ->toContain('public ?int $views');

    // Update variant makes every field optional (Sometimes + nullable + default).
    expect(file_get_contents($update))
        ->toContain('class UpdateProductData extends Data')
        ->toContain('#[Sometimes, StringType, Max(255)]')
        ->toContain('public ?string $title = null');
});

it('honours the --variant option for Data classes', function () {
    Artisan::call('zola:make-data', [
        'model'     => 'Product',
        '--fields'  => 'title:string',
        '--variant' => ['store'],
    ]);

    expect(is_file("{$this->workdir}/app/Data/StoreProductData.php"))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Data/UpdateProductData.php"))->toBeFalse();
});

it('make-crud wires fields into the model, migration and data in one run', function () {
    $exit = Artisan::call('zola:make-crud', [
        'model'    => 'Product',
        '--fields' => 'title:string, views:integer',
        '--only'   => ['controller', 'migration', 'data'],
    ]);

    expect($exit)->toBe(0);

    // Model keeps its $fillable: proves the cascade did not overwrite it field-less.
    expect(file_get_contents("{$this->workdir}/app/Models/Product.php"))
        ->toContain("'title'")
        ->toContain("'views'");

    // Full class stack.
    expect(is_file("{$this->workdir}/app/Http/Controllers/ProductController.php"))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Services/ProductService.php"))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Repositories/ProductRepository.php"))->toBeTrue();

    // Migration and both Data classes.
    expect(glob("{$this->workdir}/database/migrations/*_create_products_table.php"))->toHaveCount(1)
        ->and(is_file("{$this->workdir}/app/Data/StoreProductData.php"))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Data/UpdateProductData.php"))->toBeTrue();
});
