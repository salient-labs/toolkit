<?php

declare(strict_types=1);

namespace Lkrms\Template;

use Lkrms\Reflect\PropertyResolver;

/**
 * Implements ISettable to provide a basic implementation of __set and __unset
 *
 * Override {@see TSettable::getSettable()} to allow access to `protected`
 * variables via `__set` and `__unset`.
 *
 * The default is to deny `__set` and `__unset` for all properties.
 *
 * - If `_set<Property>($value)` is defined, it will be called instead of
 *   assigning `$value` to `<Property>`.
 * - If `_unset<Property>()` is defined, it will be called to unset `<Property>`
 *   instead of assigning `null`.
 * - The existence of `_set<Property>()` implies that `<Property>` is settable,
 *   regardless of {@see TSettable::getSettable()}'s return value.
 *
 * @package Lkrms
 * @see ISettable
 */
trait TSettable
{
    /**
     * Return a list of settable protected properties
     *
     * To make all `protected` properties settable, return
     * {@see IAccessible::ALLOW_PROTECTED}.
     *
     * @return string[]
     */
    public static function getSettable(): array
    {
        return IAccessible::ALLOW_NONE;
    }

    private function setProperty(string $action, string $name, ...$params)
    {
        return (PropertyResolver::getFor(static::class)->getPropertyActionClosure($name, $action))($this, ...$params);
    }

    final public function __set(string $name, $value): void
    {
        $this->setProperty("set", $name, $value);
    }

    final public function __unset(string $name): void
    {
        $this->setProperty("unset", $name);
    }
}
