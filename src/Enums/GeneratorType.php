<?php

namespace Zola\CrudGenerator\Enums;

enum GeneratorType: string
{
    case Model = 'Models';
    case Repository = 'Repositories';
    case Service = 'Services';
    case Controller = 'Controllers';
}
