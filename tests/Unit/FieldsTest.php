<?php

use Zola\CrudGenerator\Support\Fields;

describe('Fields::parse()', function () {
    it('parses a compact field string', function () {
        expect(Fields::parse('title:string, body:text:nullable, views:integer'))
            ->toBe([
                ['name' => 'title', 'type' => 'string', 'nullable' => false],
                ['name' => 'body', 'type' => 'text', 'nullable' => true],
                ['name' => 'views', 'type' => 'integer', 'nullable' => false],
            ]);
    });

    it('defaults a missing type to string', function () {
        expect(Fields::parse('slug'))
            ->toBe([['name' => 'slug', 'type' => 'string', 'nullable' => false]]);
    });

    it('returns an empty array for empty input', function () {
        expect(Fields::parse(null))->toBe([])
            ->and(Fields::parse(''))->toBe([])
            ->and(Fields::parse('   '))->toBe([]);
    });
});

it('round-trips through serialize()', function () {
    $raw = 'title:string,body:text:nullable';
    expect(Fields::serialize(Fields::parse($raw)))->toBe($raw);
});

it('maps types to php types, casts, blueprint methods and table names', function () {
    expect(Fields::phpType('integer'))->toBe('int')
        ->and(Fields::phpType('decimal'))->toBe('float')
        ->and(Fields::phpType('boolean'))->toBe('bool')
        ->and(Fields::phpType('date'))->toBe('\\Carbon\\Carbon')
        ->and(Fields::cast('boolean'))->toBe('boolean')
        ->and(Fields::cast('decimal'))->toBe('decimal:2')
        ->and(Fields::cast('string'))->toBeNull()
        ->and(Fields::blueprint('datetime'))->toBe('dateTime')
        ->and(Fields::blueprint('foreignId'))->toBe('foreignId')
        ->and(Fields::tableName('Product'))->toBe('products')
        ->and(Fields::tableName('Category'))->toBe('categories');
});

it('derives validation rules from type and nullability', function () {
    expect(Fields::validation(['name' => 't', 'type' => 'string', 'nullable' => false]))
        ->toBe(['Required', 'StringType', 'Max(255)']);

    expect(Fields::validation(['name' => 'v', 'type' => 'integer', 'nullable' => true]))
        ->toBe(['Nullable', 'IntegerType']);
});
