<?php

use Zola\CrudGenerator\CrudGenerator;
use Zola\CrudGenerator\Enums\GeneratorType;

beforeEach(function () {
    $this->generator = new CrudGenerator();
});

describe('packagePath()', function () {
    it('resolves to the package root when no sub-path is given', function () {
        expect($this->generator->packagePath())
            ->toEndWith('crud-generator')
            ->and(is_dir($this->generator->packagePath()))->toBeTrue();
    });

    it('resolves a real file that lives inside the package', function () {
        expect(is_file($this->generator->packagePath('stubs/ZolaModel.stub')))->toBeTrue();
    });

    it('is not confused by a leading slash', function () {
        expect($this->generator->packagePath('/stubs/ZolaModel.stub'))
            ->toBe($this->generator->packagePath('stubs/ZolaModel.stub'));
    });
});

describe('isModuleEnabled()', function () {
    it('is false when the module flag is off', function () {
        config()->set('crud-generator.is_laravel_module', false);
        expect($this->generator->isModuleEnabled())->toBeFalse();
    });

    it('is true when the module flag is on', function () {
        config()->set('crud-generator.is_laravel_module', true);
        expect($this->generator->isModuleEnabled())->toBeTrue();
    });
});

describe('getNamespace() (app mode)', function () {
    beforeEach(fn () => config()->set('crud-generator.is_laravel_module', false));

    it('builds an App\\* namespace per generator type', function (GeneratorType $type, string $expected) {
        expect($this->generator->getNamespace($type, null))->toBe($expected);
    })->with([
        'model'      => [GeneratorType::Model, 'App\\Models'],
        'repository' => [GeneratorType::Repository, 'App\\Repositories'],
        'service'    => [GeneratorType::Service, 'App\\Services'],
    ]);
});

describe('getNamespace() (module mode)', function () {
    beforeEach(fn () => config()->set('crud-generator.is_laravel_module', true));

    it('nests the type under Modules\\{module}', function () {
        expect($this->generator->getNamespace(GeneratorType::Model, 'Blog'))
            ->toBe('Modules\\Blog\\Models');
    });
});

describe('getTargetDir()', function () {
    it('writes under app/ in app mode', function () {
        config()->set('crud-generator.is_laravel_module', false);
        expect($this->generator->getTargetDir(GeneratorType::Repository, null))
            ->toBe('app/Repositories');
    });

    it('writes under Modules/{module}/app in module mode', function () {
        config()->set('crud-generator.is_laravel_module', true);
        expect($this->generator->getTargetDir(GeneratorType::Service, 'Blog'))
            ->toBe('Modules/Blog/app/Services');
    });
});

describe('getModelClassName()', function () {
    it('points at App\\Models in app mode', function () {
        config()->set('crud-generator.is_laravel_module', false);
        expect($this->generator->getModelClassName('Product', null))
            ->toBe('App\\Models\\Product');
    });

    it('points at the module Models namespace in module mode', function () {
        config()->set('crud-generator.is_laravel_module', true);
        expect($this->generator->getModelClassName('Product', 'Blog'))
            ->toBe('Modules\\Blog\\Models\\Product');
    });
});

describe('generated-file tracking', function () {
    it('rolls back only the files it created, sparing pre-existing ones', function () {
        $dir = sys_get_temp_dir() . '/zola-rollback-' . uniqid();
        mkdir($dir, 0777, true);

        // A file the caller already had, not written through the tracker.
        file_put_contents("{$dir}/existing.php", 'original');

        $this->generator->resetGeneratedFiles();
        // Overwriting an existing file must NOT track it (so rollback spares it).
        $this->generator->writeGeneratedFile("{$dir}/existing.php", 'changed');
        // A brand new file IS tracked.
        $this->generator->writeGeneratedFile("{$dir}/new.php", 'new');

        $removed = $this->generator->rollbackGeneratedFiles();

        expect($removed)->toBe(["{$dir}/new.php"])
            ->and(is_file("{$dir}/new.php"))->toBeFalse()
            ->and(is_file("{$dir}/existing.php"))->toBeTrue();

        @unlink("{$dir}/existing.php");
        @rmdir($dir);
    });

    it('reports a write failure without throwing', function () {
        // Target directory does not exist, so the write cannot succeed.
        $missing = sys_get_temp_dir() . '/zola-missing-' . uniqid() . '/nope.php';

        expect($this->generator->writeGeneratedFile($missing, 'x'))->toBeFalse();
    });
});

describe('checkClassExistance()', function () {
    beforeEach(fn () => config()->set('crud-generator.is_laravel_module', false));

    it('returns false for a class that does not exist', function () {
        expect($this->generator->checkClassExistance(GeneratorType::Model, 'DefinitelyMissingModel'))
            ->toBeFalse();
    });

    it('returns true once the class is resolvable', function () {
        // Alias any real class into the App\Models namespace the method checks.
        class_alias(CrudGenerator::class, 'App\\Models\\AliasedDummyModel');

        expect($this->generator->checkClassExistance(GeneratorType::Model, 'AliasedDummyModel'))
            ->toBeTrue();
    });

    it('treats a class as absent when autoloading it throws', function () {
        // Simulate a stale optimized classmap: autoloading the class errors
        // because its mapped file was removed.
        $loader = function ($class) {
            if ($class === 'App\\Repositories\\GhostRepository') {
                throw new \ErrorException('include(app/Repositories/GhostRepository.php): Failed to open stream');
            }
        };
        spl_autoload_register($loader, true, true);

        try {
            expect($this->generator->checkClassExistance(GeneratorType::Repository, 'GhostRepository'))
                ->toBeFalse();
        } finally {
            spl_autoload_unregister($loader);
        }
    });

    it('resolves against the module namespace in module mode', function () {
        config()->set('crud-generator.is_laravel_module', true);

        // The class lives under the module namespace, keyed by the module name (not the class name).
        class_alias(CrudGenerator::class, 'Modules\\Blog\\Models\\AliasedModuleModel');

        expect($this->generator->checkClassExistance(GeneratorType::Model, 'AliasedModuleModel', 'Blog'))
            ->toBeTrue()
            ->and($this->generator->checkClassExistance(GeneratorType::Model, 'AliasedModuleModel', 'WrongModule'))
            ->toBeFalse();
    });
});
