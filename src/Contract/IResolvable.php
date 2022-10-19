<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Closure;

/**
 * Normalises property names
 *
 */
interface IResolvable
{
    /**
     * Returns a closure to normalise a property name
     *
     * Arguments after `$name` may be ignored.
     *
     * @return Closure
     * ```php
     * function (string $name, bool $aggressive = true, string ...$hints): string
     * ```
     */
    public static function getNormaliser(): Closure;

}
