<?php

declare(strict_types=1);

namespace Eleph\Gen\Php\Ir;

enum TriggerEvent: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
