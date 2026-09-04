# Zola CRUD Generator

[![Tests](https://github.com/Ilhammeru/crud-generator/actions/workflows/tests.yml/badge.svg)](https://github.com/Ilhammeru/crud-generator/actions/workflows/tests.yml)

Artisan generators that scaffold a full CRUD stack (model, migration, repository,
service, controller and [spatie/laravel-data](https://spatie.be/docs/laravel-data)
Data classes) following a repository/service layering convention. Built for
Laravel 12 and optionally aware of the
[Laravel Modules](https://github.com/nWidart/laravel-modules) directory layout.

- `zola:make-crud` scaffolds the whole stack in one command (interactive or headless)
- `zola:make-controller` generates a controller, plus the service, repository, model and Data classes it needs
- `zola:make-service` generates a service (and its repository, model and Data classes)
- `zola:make-repository` generates a repository (and its model, if missing)
- `zola:make-model` generates an Eloquent model, with `$fillable`/`$casts` from `--fields`
- `zola:make-migration` generates a timestamped migration from `--fields`
- `zola:make-data` generates `Store{Model}Data` and `Update{Model}Data` (laravel-data) with validation rules

Generated repositories extend a shared `BaseRepository` that keeps all database
access in one place. Generated services expose `list()`, `store()`, `update()` and
`delete()`, and controllers expose the matching REST actions, validated through
the laravel-data classes.

## Requirements

- PHP `^8.2`
- Laravel `^12.0` (`illuminate/support`, `illuminate/contracts`)
- [`spatie/laravel-data`](https://spatie.be/docs/laravel-data) `^4.0` (installed automatically as a dependency)

## Installation

The service provider is auto-discovered, so no manual registration is needed
once the package is required.

### From Packagist

```bash
composer require zola/crud-generator
```

### Local path (monorepo / active development)

If the package lives inside your app (for example under `packages/zola/`), point
Composer at it with a path repository in the app's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/zola/*",
        "options": { "symlink": true }
    }
],
"require": {
    "zola/crud-generator": "@dev"
}
```

Then:

```bash
composer update zola/crud-generator --with-all-dependencies
```

The `symlink` option means edits inside `packages/zola/crud-generator` are picked
up immediately, with no reinstall.

## Configuration

Publish the config file (optional):

```bash
php artisan vendor:publish --tag=crud-generator-config
```

This writes `config/crud-generator.php`:

```php
return [
    'enabled'           => true,
    'is_laravel_module' => false,
];
```

| Key                 | Type   | Default | Purpose                                                                 |
|---------------------|--------|---------|-------------------------------------------------------------------------|
| `is_laravel_module` | `bool` | `false` | When `true`, generate into `Modules/{Module}/app/...` instead of `app/`. |
| `enabled`           | `bool` | `true`  | General on/off flag you can read in your own code via `config()`.       |

## Usage

### Generate a full CRUD (recommended)

Run interactively and answer the prompts (model name, which parts to generate,
fields, and which Data classes):

```bash
php artisan zola:make-crud
```

Or run it headless by passing a model and options:

```bash
php artisan zola:make-crud Product --fields="title:string, price:decimal:nullable, active:boolean"
```

`make-crud` generates the model (with `$fillable`/`$casts`), the migration, the
repository, the service, the controller and the `Store`/`Update` Data classes,
and wires them together. It is **transactional**: if any step fails, every file
it created during that run is rolled back so you are never left with a
half-generated stack.

| Option      | Purpose                                                                              |
|-------------|-------------------------------------------------------------------------------------|
| `--only=*`  | Limit generation to specific parts: `model`, `migration`, `repository`, `service`, `controller`, `data`. |
| `--fields=` | Field definitions (see below).                                                      |
| `--module=` | Module name, required when module mode is enabled.                                   |
| `--data=*`  | Which Data variants to generate when generating data standalone: `store`, `update`. |

```bash
# Only the model and migration, with fields
php artisan zola:make-crud Product --only=model,migration --fields="title:string, stock:integer"
```

### Field syntax

Fields are a comma-separated list of `name:type[:nullable]`. The type defaults to
`string` when omitted, and the third segment marks the column nullable.

```
title:string, body:text:nullable, price:decimal, active:boolean, published_at:datetime:nullable, user_id:foreignId
```

Each type maps consistently across the model cast, the migration column and the
Data property:

| Type        | Migration column         | Model cast    | Data property type |
|-------------|--------------------------|---------------|--------------------|
| `string`    | `string`                 | (none)        | `string`           |
| `text`      | `text`                   | (none)        | `string`           |
| `integer`   | `integer`                | `integer`     | `int`              |
| `decimal`   | `decimal(8, 2)`          | `decimal:2`   | `float`            |
| `boolean`   | `boolean`                | `boolean`     | `bool`             |
| `date`      | `date`                   | `date`        | `\Carbon\Carbon`   |
| `datetime`  | `dateTime`               | `datetime`    | `\Carbon\Carbon`   |
| `foreignId` | `foreignId`              | `integer`     | `int`              |

### Generate a model

```bash
php artisan zola:make-model Product --fields="title:string, views:integer"
```

Creates `app/Models/Product.php`:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'title',
        'views',
    ];

    protected $casts = [
        'views' => 'integer',
    ];
}
```

Model names must be PascalCase. Names containing a dash or a space are rejected.

### Generate a migration

```bash
php artisan zola:make-migration Product --fields="title:string, price:decimal:nullable"
```

Creates `database/migrations/<timestamp>_create_products_table.php` with one
column per field (nullable fields get `->nullable()`), an `id()` and
`timestamps()`.

### Generate Data classes

```bash
php artisan zola:make-data Product --fields="title:string, views:integer:nullable"
```

Creates `app/Data/StoreProductData.php` and `app/Data/UpdateProductData.php`.
`Store` keeps each field's required/nullable rule; `Update` marks every field
optional (`#[Sometimes]`) for partial updates. Validation attributes are derived
from the field type and nullability. Pass `--variant=store` or `--variant=update`
to generate only one.

### Generate a repository

```bash
php artisan zola:make-repository Product
```

Creates `app/Repositories/ProductRepository.php`, and `app/Models/Product.php`
first if the model does not already exist. The `Repository` suffix is added
automatically, so `zola:make-repository Product` and
`zola:make-repository ProductRepository` both produce `ProductRepository`.

### Generate a service

```bash
php artisan zola:make-service Product
```

Creates `app/Services/ProductService.php`, plus the repository, model and Data
classes if they are missing:

```php
namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Data\StoreProductData;
use App\Data\UpdateProductData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $repo
    ) {}

    public function list(): LengthAwarePaginator
    {
        return $this->repo->paginate();
    }

    public function store(StoreProductData $data): Product
    {
        return $this->repo->store($data->toArray());
    }

    public function update(Product $model, UpdateProductData $data): Product
    {
        return $this->repo->update($model, $data->toArray());
    }

    public function delete(Product $model): bool
    {
        return $this->repo->delete($model);
    }
}
```

### Generate a controller

```bash
php artisan zola:make-controller Product Product
```

Creates `app/Http/Controllers/ProductController.php`, plus the service,
repository, model and Data classes. The actions call through to the service and
type-hint the Data classes, so validation runs on injection:

```php
public function index(): JsonResponse
{
    return response()->json($this->service->list());
}

public function store(StoreProductData $data): JsonResponse
{
    return response()->json($this->service->store($data), 201);
}

public function update(UpdateProductData $data, Product $product): JsonResponse
{
    return response()->json($this->service->update($product, $data));
}

public function destroy(Product $product): JsonResponse
{
    $this->service->delete($product);

    return response()->json(null, 204);
}
```

### Command reference

| Command                | Arguments                              | Options                                      |
|------------------------|----------------------------------------|----------------------------------------------|
| `zola:make-crud`       | `model?`                               | `--only=*` `--module=` `--fields=` `--data=*` |
| `zola:make-controller` | `name` `model` `moduleName?` `service?` | none                                         |
| `zola:make-service`    | `serviceName` `moduleName?`            | `--model=` `--without-repository=`           |
| `zola:make-repository` | `repoName` `modelName?` `moduleName?`  | none                                         |
| `zola:make-model`      | `model` `moduleName?`                  | `--fields=`                                  |
| `zola:make-migration`  | `model` `moduleName?`                  | `--fields=`                                  |
| `zola:make-data`       | `model` `moduleName?`                  | `--fields=` `--variant=*`                    |

## Module mode

Set `is_laravel_module` to `true` to target the Laravel Modules layout. In module
mode the module name argument (or `--module=` for `make-crud`) becomes required.

```bash
php artisan zola:make-crud Product --module=Blog
php artisan zola:make-model Product Blog
```

| Mode          | Namespace                     | Path                                  |
|---------------|-------------------------------|---------------------------------------|
| App (default) | `App\Models\Product`          | `app/Models/Product.php`              |
| Module        | `Modules\Blog\Models\Product` | `Modules/Blog/app/Models/Product.php` |

Controllers live under `Http\Controllers` (for example `App\Http\Controllers` or
`Modules\Blog\Http\Controllers`), mirroring the directory.

## The BaseRepository

Every generated repository extends `Zola\CrudGenerator\Repositories\BaseRepository`,
a generic data-access layer. Callers describe the query with an array of
parameters rather than writing the query in the service:

```php
$this->repo->get([
    'with'    => ['category'],
    'where'   => ['is_active' => true],
    'orderBy' => ['created_at' => 'desc'],
    'take'    => 20,
]);
```

Available methods: `get`, `paginate`, `show`, `findById`, `store`, `update`,
`save`, `delete`, `firstOrNew`.

Supported `$params` keys include `with`, `where`, `whereIn`, `whereHas`,
`orWhereHas`, `withWhereHas`, `select`, `orderBy`, `orderByRaw`, `skip`, `take`
and `scope`. See the docblock on `BaseRepository` for the exact accepted shapes.

## Note on optimized autoloaders

If your app runs with an optimized or authoritative Composer classmap
(`composer install -o` / `-a`), run `composer dump-autoload` after generating (or
rolling back) classes so the autoloader picks up the changes.

## Testing

The package ships with a Pest test suite running on Orchestra Testbench.

```bash
composer install
composer test
```

## License

Released under the [MIT License](LICENSE.md).
