<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Closure;
use Lkrms\Facade\Convert;

/**
 * Implements IResolvable to normalise property names
 *
 * @see \Lkrms\Contract\IResolvable
 */
trait TResolvable
{
    public static function getNormaliser(): Closure
    {
        return fn(string $name): string => Convert::toSnakeCase($name);
    }
}
