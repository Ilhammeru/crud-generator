<?php

namespace Zola\CrudGenerator\Enums;

/**
 * The kinds of class the generator can produce.
 *
 * Each case value doubles as the namespace segment and directory name used
 * when resolving where a generated class lives (e.g. GeneratorType::Model
 * maps to "App\Models" and "app/Models").
 */
enum GeneratorType: string
{
    case Model = 'Models';
    case Repository = 'Repositories';
    case Service = 'Services';
    case Controller = 'Controllers';
    case Data = 'Data';
}
