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
     * Returns a closure to normalise a given property name
     *
     * @return Closure
     * ```php
     * function (string $name): string
     * ```
     */
    public static function getNormaliser(): Closure;

}
