<?php

use Zola\CrudGenerator\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Bind the Testbench-powered TestCase to every test in the Unit and Feature
| suites, so `$this` inside a test is a booted Laravel application.
|
*/

uses(TestCase::class)->in('Unit', 'Feature');
