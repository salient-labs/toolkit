<?php

declare(strict_types=1);

namespace Lkrms\Template;

/**
 * Provides access to declared properties via alternative names
 *
 * @package Lkrms
 */
interface IResolvable
{
    /**
     * Convert a property name to its normalised form
     *
     * Returns the value to use when comparing `$name` with other normalised
     * property names to determine whether or not they resolve to the same
     * property.
     *
     * @param string $name
     * @return string
     */
    public static function normalisePropertyName(string $name): string;

    public function __set(string $name, $value): void;

    public function __get(string $name);

    public function __isset(string $name): bool;

    public function __unset(string $name): void;
}

