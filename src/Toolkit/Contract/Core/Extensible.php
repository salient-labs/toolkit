<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * Reads and writes arbitrary undeclared properties
 */
interface Extensible
{
    /**
     * Set the value of an undeclared property
     *
     * @param mixed $value
     */
    public function setMetaProperty(string $name, $value): void;

    /**
     * Get the value of an undeclared property
     *
     * @return mixed `null` if the undeclared property is not set.
     */
    public function getMetaProperty(string $name);

    /**
     * True if an undeclared property is set
     */
    public function isMetaPropertySet(string $name): bool;

    /**
     * Unset an undeclared property
     */
    public function unsetMetaProperty(string $name): void;

    /**
     * Get an array that maps the object's undeclared property names to their
     * current values
     *
     * @return array<string,mixed>
     */
    public function getMetaProperties(): array;

    /**
     * Unset the object's undeclared properties
     *
     * @return $this
     */
    public function clearMetaProperties();

    /**
     * Set the value of an undeclared property
     *
     * @param mixed $value
     */
    public function __set(string $name, $value): void;

    /**
     * Get the value of an undeclared property
     *
     * @return mixed `null` if the undeclared property is not set.
     */
    public function __get(string $name);

    /**
     * True if an undeclared property is set
     */
    public function __isset(string $name): bool;

    /**
     * Unset an undeclared property
     */
    public function __unset(string $name): void;
}
