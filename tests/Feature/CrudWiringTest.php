<?php

use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Service and controller wiring tests
|--------------------------------------------------------------------------
|
| The service stub exposes list()/store()/update()/delete() backed by the
| repository and the laravel-data Data classes; the controller stub exposes
| index/store/update/destroy that call through to the service. These tests
| assert that wiring, and that the Data dependency is generated alongside.
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

it('wires the service to the repository and Data classes', function () {
    Artisan::call('zola:make-crud', ['model' => 'Product', '--only' => ['controller']]);

    expect(file_get_contents("{$this->workdir}/app/Services/ProductService.php"))
        ->toContain('use App\\Data\\StoreProductData;')
        ->toContain('use App\\Data\\UpdateProductData;')
        ->toContain('public function list(): LengthAwarePaginator')
        ->toContain('return $this->repo->paginate();')
        ->toContain('public function store(StoreProductData $data): Product')
        ->toContain('return $this->repo->store($data->toArray());')
        ->toContain('public function update(Product $model, UpdateProductData $data): Product')
        ->toContain('public function delete(Product $model): bool')
        ->toContain('return $this->repo->delete($model);');
});

it('wires the controller actions to the service', function () {
    Artisan::call('zola:make-crud', ['model' => 'Product', '--only' => ['controller']]);

    expect(file_get_contents("{$this->workdir}/app/Http/Controllers/ProductController.php"))
        ->toContain('public function index(): JsonResponse')
        ->toContain('return response()->json($this->service->list());')
        ->toContain('public function store(StoreProductData $data): JsonResponse')
        ->toContain('$this->service->store($data)')
        ->toContain('public function update(UpdateProductData $data, Product $product): JsonResponse')
        ->toContain('$this->service->update($product, $data)')
        ->toContain('public function destroy(Product $product): JsonResponse')
        ->toContain('$this->service->delete($product);');
});

it('generates the Data dependency when a service is created on its own', function () {
    Artisan::call('zola:make-service', ['serviceName' => 'Product']);

    expect(is_file("{$this->workdir}/app/Data/StoreProductData.php"))->toBeTrue()
        ->and(is_file("{$this->workdir}/app/Data/UpdateProductData.php"))->toBeTrue();
});
