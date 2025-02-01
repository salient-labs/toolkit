<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * @api
 */
interface Temporal
{
    /**
     * Get properties that accept date and time values
     *
     * Date and time values are always converted to {@see DateTimeImmutable}
     * instances for declared and "magic" properties that accept
     * {@see DateTimeImmutable} and/or return {@see DateTimeInterface}. This
     * method may be used to nominate untyped properties for the same treatment.
     *
     * If `["*"]` is returned and the class has no declared or "magic"
     * properties with compatible type hints, date and time values are converted
     * to {@see DateTimeImmutable} instances for all properties.
     *
     * Properties returned must accept {@see DateTimeImmutable} and/or return
     * {@see DateTimeInterface}.
     *
     * @return string[]
     */
    public static function getDateProperties(): array;
}
