<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * Writes private, protected or undeclared properties
 */
interface Writable
{
    /**
     * Get a list of writable properties
     *
     * `["*"]` expands to all `protected` properties.
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
