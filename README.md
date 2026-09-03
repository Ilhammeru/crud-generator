# Zola CRUD Generator

[![Tests](https://github.com/zola/crud-generator/actions/workflows/tests.yml/badge.svg)](https://github.com/zola/crud-generator/actions/workflows/tests.yml)

Artisan generators that scaffold a Model, a Repository and a Service in one go,
following a repository/service layering convention. Built for Laravel 12 and
optionally aware of the [Laravel Modules](https://github.com/nWidart/laravel-modules)
directory layout.

- `zola:make-model` generates an Eloquent model
- `zola:make-repository` generates a repository (and its model, if missing)
- `zola:make-service` generates a service (and its repository and model, if missing)

Generated repositories extend a shared `BaseRepository` that keeps all database
access in one place: services pass selection columns, where conditions,
eager-loads and ordering in as parameters, and the repository runs the query.

## Requirements

- PHP `^8.2`
- Laravel `^12.0` (`illuminate/support`, `illuminate/contracts`)

## Installation

The service provider is auto-discovered, so no manual registration is needed
once the package is required.

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
composer update zola/crud-generator
```

The `symlink` option means edits inside `packages/zola/crud-generator` are picked
up immediately, with no reinstall.

### From GitHub (VCS repository)

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/zola/crud-generator"
    }
]
```

```bash
composer require zola/crud-generator:@dev
```

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

### Generate a model

```bash
php artisan zola:make-model Product
```

Creates `app/Models/Product.php`:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [];
}
```

Model names must be PascalCase. Names containing a dash or a space are rejected.

### Generate a repository

```bash
php artisan zola:make-repository Product
```

Creates `app/Repositories/ProductRepository.php`, and `app/Models/Product.php`
first if the model does not already exist:

```php
namespace App\Repositories;

use Zola\CrudGenerator\Repositories\BaseRepository;
use App\Models\Product;

class ProductRepository extends BaseRepository
{
    public function __construct(Product $model)
    {
        return parent::__construct($model);
    }
}
```

The `Repository` suffix is added automatically, so `zola:make-repository Product`
and `zola:make-repository ProductRepository` both produce `ProductRepository`.

### Generate a service

```bash
php artisan zola:make-service Product
```

Creates `app/Services/ProductService.php`, plus the repository and model if they
are missing:

```php
namespace App\Services;

use App\Repositories\ProductRepository;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $repo
    ) {}
}
```

Pass `--model=` to bind an existing model instead of generating one:

```bash
php artisan zola:make-service Product --model=Product
```

### Command reference

| Command                 | Arguments                        | Options                              |
|-------------------------|----------------------------------|--------------------------------------|
| `zola:make-model`       | `model` `moduleName?`            | none                                 |
| `zola:make-repository`  | `repoName` `modelName?` `moduleName?` | none                            |
| `zola:make-service`     | `serviceName` `moduleName?`      | `--model=` `--without-repository=`   |

## Module mode

Set `is_laravel_module` to `true` to target the Laravel Modules layout. In module
mode the module name argument becomes required.

```bash
php artisan zola:make-model Product Blog
```

| Mode        | Namespace               | Path                            |
|-------------|-------------------------|---------------------------------|
| App (default) | `App\Models\Product`  | `app/Models/Product.php`        |
| Module      | `Modules\Blog\Models\Product` | `Modules/Blog/app/Models/Product.php` |

## The BaseRepository

Every generated repository extends `Zola\CrudGenerator\Repositories\BaseRepository`,
a generic data-access layer. Callers describe the query with an array of
parameters rather than writing the query in the service:

```php
class ProductService
{
    public function __construct(
        private readonly ProductRepository $repo
    ) {}

    public function activeProducts(): Collection
    {
        return $this->repo->get([
            'with'    => ['category'],
            'where'   => ['is_active' => true],
            'orderBy' => ['created_at' => 'desc'],
            'take'    => 20,
        ]);
    }
}
```

Available methods: `get`, `paginate`, `show`, `findById`, `store`, `update`,
`save`, `delete`, `firstOrNew`.

Supported `$params` keys include `with`, `where`, `whereIn`, `whereHas`,
`orWhereHas`, `withWhereHas`, `select`, `orderBy`, `orderByRaw`, `skip`, `take`
and `scope`. See the docblock on `BaseRepository` for the exact accepted shapes.

## Testing

The package ships with a Pest test suite running on Orchestra Testbench.

```bash
composer install
composer test
```

## License

Proprietary.
