<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

/**
 * @api
 */
interface Readable
{
    /**
     * Get readable properties
     *
     * Returning `["*"]` has the same effect as returning every `protected`
     * property of the class.
     *
     * @return string[]
     */
    public static function getReadableProperties(): array;

    /**
     * Get the value of a property, or null if it is not set
     *
     * @return mixed
     */
    public function __get(string $name);

    /**
     * Check if a property is set
     */
    public function __isset(string $name): bool;
}
