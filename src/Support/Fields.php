<?php

namespace Zola\CrudGenerator\Support;

use Illuminate\Support\Str;

/**
 * Parsing and type-mapping for CRUD field definitions.
 *
 * A field is described by a name, a logical type (string, text, integer,
 * decimal, boolean, date, datetime, foreignId) and a nullable flag. This class
 * is the single place that maps those logical types onto PHP types, Eloquent
 * casts, migration Blueprint methods and laravel-data validation attributes.
 */
class Fields
{
    /**
     * Parse a compact field string into structured definitions.
     *
     * Format: "name:type[:nullable], other:type, ...". Missing types default
     * to "string"; the third segment enables nullable when it is "nullable".
     *
     * @return array<int, array{name:string, type:string, nullable:bool}>
     */
    public static function parse(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $fields = [];
        foreach (explode(',', $raw) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            $parts = array_map('trim', explode(':', $chunk));
            $name = $parts[0];
            if ($name === '') {
                continue;
            }

            $fields[] = [
                'name' => $name,
                'type' => $parts[1] ?? 'string',
                'nullable' => isset($parts[2]) && strtolower($parts[2]) === 'nullable',
            ];
        }

        return $fields;
    }

    /**
     * Serialize structured field definitions back into the compact string.
     *
     * @param  array<int, array{name:string, type:string, nullable:bool}>  $fields
     */
    public static function serialize(array $fields): string
    {
        return implode(',', array_map(
            fn ($f) => $f['name'] . ':' . $f['type'] . ($f['nullable'] ? ':nullable' : ''),
            $fields
        ));
    }

    /**
     * The plural, snake_case table name for a model.
     */
    public static function tableName(string $model): string
    {
        return Str::snake(Str::pluralStudly($model));
    }

    /**
     * The PHP type used for a Data property.
     */
    public static function phpType(string $type): string
    {
        return match ($type) {
            'integer', 'foreignId' => 'int',
            'decimal' => 'float',
            'boolean' => 'bool',
            'date', 'datetime' => '\\Carbon\\Carbon',
            default => 'string',
        };
    }

    /**
     * The Eloquent cast for a field type, or null when none is needed.
     */
    public static function cast(string $type): ?string
    {
        return match ($type) {
            'integer', 'foreignId' => 'integer',
            'decimal' => 'decimal:2',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'datetime',
            default => null,
        };
    }

    /**
     * The migration Blueprint method for a field type.
     */
    public static function blueprint(string $type): string
    {
        return match ($type) {
            'text' => 'text',
            'integer' => 'integer',
            'decimal' => 'decimal',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'dateTime',
            'foreignId' => 'foreignId',
            default => 'string',
        };
    }

    /**
     * laravel-data validation attribute names for a field.
     *
     * The first entry reflects presence (Required or Nullable); the rest are
     * type rules. Names such as "Max(255)" carry their argument inline.
     *
     * @param  array{name:string, type:string, nullable:bool}  $field
     * @return array<int, string>
     */
    public static function validation(array $field): array
    {
        $rules = [$field['nullable'] ? 'Nullable' : 'Required'];

        $rules[] = match ($field['type']) {
            'integer', 'foreignId' => 'IntegerType',
            'decimal' => 'Numeric',
            'boolean' => 'BooleanType',
            'date', 'datetime' => 'Date',
            default => 'StringType',
        };

        if ($field['type'] === 'string') {
            $rules[] = 'Max(255)';
        }

        return $rules;
    }

    /**
     * Render an array body for a stub, indented for readability.
     *
     * Returns "" for an empty list so the stub renders "[]"; otherwise the
     * items are placed on their own lines with a trailing comma.
     *
     * @param  array<int, string>  $items  Already-formatted entries.
     */
    public static function renderList(array $items, int $indent = 8): string
    {
        if ($items === []) {
            return '';
        }

        $pad = str_repeat(' ', $indent);
        $close = str_repeat(' ', max(0, $indent - 4));

        return "\n" . implode('', array_map(fn ($i) => "{$pad}{$i},\n", $items)) . $close;
    }
}
