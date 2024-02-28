<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * Has declared or undeclared properties that accept date and time values
 */
interface Temporal
{
    /**
     * Get properties that accept date and time values
     *
     * Return `["*"]` to detect date and time values automatically.
     *
     * @return string[]
     */
    public static function getDateProperties(): array;
}
