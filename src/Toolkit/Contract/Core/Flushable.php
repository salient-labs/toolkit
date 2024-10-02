<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
 */
interface Flushable
{
    /**
     * Reset static properties
     */
    public static function flushStatic(): void;
}
