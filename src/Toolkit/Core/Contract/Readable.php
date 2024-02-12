<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * Reads private, protected or undeclared properties
 */
interface Readable
{
    /**
     * Get a list of readable properties
     *
     * `["*"]` expands to all `protected` properties.
     *
     * @return string[]
     */
    public static function getReadableProperties(): array;

    /**
     * Get the value of a property
     *
     * @return mixed `null` if the property is not set.
     */
    public function __get(string $name);

    /**
     * True if a property is set
     */
    public function __isset(string $name): bool;
}
