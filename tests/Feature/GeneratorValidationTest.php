<?php

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Generator validation (sad paths)
|--------------------------------------------------------------------------
|
| These commands bail out before touching the filesystem, so they need no
| working-directory harness. They assert the FAILURE exit code and message,
| which is only possible now that the commands `return self::FAILURE`
| instead of calling exit().
|
*/

it('fails when module mode is enabled but no module name is provided', function () {
    config()->set('crud-generator.is_laravel_module', true);

    $exit = Artisan::call('zola:make-model', ['model' => 'Product']);

    expect($exit)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Module name is required');
});

it('rejects a model name that is not camel case', function () {
    config()->set('crud-generator.is_laravel_module', false);

    $exit = Artisan::call('zola:make-model', ['model' => 'my-model']);

    expect($exit)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Only camel case allowed');
});
