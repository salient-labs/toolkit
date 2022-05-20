<?php

declare(strict_types=1);

namespace Lkrms\Core\Mixin;

use Lkrms\Util\Convert;

/**
 * Implements IResolvable to normalise property names
 *
 * @see \Lkrms\Core\Contract\IResolvable
 */
trait TResolvable
{
    public static function normaliseProperty(string $name): string
    {
        return Convert::toSnakeCase($name);
    }
}
