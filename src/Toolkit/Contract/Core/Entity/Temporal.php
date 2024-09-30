<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

use DateTimeInterface;

/**
 * @api
 */
interface Temporal
{
    /**
     * Get properties that accept date and time values
     *
     * If `["*"]` is returned, a {@see DateTimeInterface} instance may be
     * applied to any accessible property.
     *
     * @return string[]
     */
    public static function getDateProperties(): array;
}
