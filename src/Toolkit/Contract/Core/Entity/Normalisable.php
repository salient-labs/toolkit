<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

/**
 * @api
 */
interface Normalisable
{
    /**
     * Normalise a property name
     *
     * Arguments after `$name` may be ignored.
     */
    public static function normaliseProperty(
        string $name,
        bool $greedy = true,
        string ...$hints
    ): string;
}
