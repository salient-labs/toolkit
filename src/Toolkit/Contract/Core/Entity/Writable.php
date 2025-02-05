<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

/**
 * @api
 */
interface Writable
{
    /**
     * Get writable properties
     *
     * Returning `["*"]` has the same effect as returning every `protected`
     * property of the class.
     *
     * @return string[]
     */
    public static function getWritableProperties(): array;

    /**
     * Set the value of a property
     *
     * @param mixed $value
     */
    public function __set(string $name, $value): void;

    /**
     * Unset a property
     */
    public function __unset(string $name): void;
}
