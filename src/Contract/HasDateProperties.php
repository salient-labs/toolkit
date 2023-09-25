<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Uses a DateFormatter to serialize and deserialize accessible properties
 */
interface HasDateProperties
{
    /**
     * Get a list of date properties, or ["*"] to convert all accessible
     * properties to and from DateTimeImmutable objects
     *
     * @return string[]
     */
    public static function getDateProperties(): array;
}
