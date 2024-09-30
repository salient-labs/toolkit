<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

/**
 * @api
 */
interface Extensible
{
    /**
     * Get the property that stores dynamic properties
     *
     * The property returned must accept `array<string,mixed>` or
     * `\ArrayAccess<string,mixed>`.
     */
    public static function getDynamicPropertiesProperty(): string;

    /**
     * Get the property that stores dynamic property names
     *
     * The property returned must accept `array<string,string>` or
     * `\ArrayAccess<string,string>`.
     */
    public static function getDynamicPropertyNamesProperty(): string;

    /**
     * Get the object's dynamic properties
     *
     * @return array<string,mixed> An array that maps property names to values.
     */
    public function getDynamicProperties(): array;

    /**
     * Set the object's dynamic properties
     *
     * @param array<string,mixed> $values An array that maps property names to
     * values.
     */
    public function setDynamicProperties(array $values): void;

    /**
     * Set the value of a property
     *
     * @param mixed $value
     */
    public function __set(string $name, $value): void;

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

    /**
     * Unset a property
     */
    public function __unset(string $name): void;
}
