<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use DateTimeInterface;

/**
 * Has properties that store date and time values
 *
 * The properties need not be declared if the class uses property overloading.
 */
interface Temporal
{
    /**
     * Get properties that store date and time values, or ["*"] to detect date
     * and time values automatically
     *
     * Properties should accept values of type {@see DateTimeInterface}`|null`.
     *
     * @return string[]
     */
    public static function getDateProperties(): array;
}
