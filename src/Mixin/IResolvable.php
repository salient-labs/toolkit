<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

/**
 * Provides access to alternate forms of declared properties
 *
 * For example, an implementor might map a non-existent property called
 * `first_name` to a declared one called `FirstName`.
 *
 * @package Lkrms
 */
interface IResolvable
{
    /**
     * Convert the name of an undeclared property to its canonical form
     *
     * @param string $name
     * @return string|false The name of the property to access when overloading
     * `$name`, or `false` if `$name` could not be resolved. Does not imply the
     * property actually exists or has been declared.
     */
    public function ResolvePropertyName(string $name);

    public function __set(string $name, mixed $value): void;

    public function __get(string $name): mixed;

    public function __isset(string $name): bool;

    public function __unset(string $name): void;
}

