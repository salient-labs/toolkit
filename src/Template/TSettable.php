<?php

declare(strict_types=1);

namespace Lkrms\Template;

/**
 * A basic implementation of __set and __unset
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
    use TGettable;

    /**
     * Return a list of settable protected properties
     *
     * To make all `protected` properties settable, return
     * {@see IAccessible::ALLOW_ALL_PROTECTED}.
     *
     * @return null|string[]
     */
    public function getSettable(): ?array
    {
        return IAccessible::ALLOW_NONE;
    }

    private function setProperty(string $action, string $name, ...$params)
    {
        return ($this->getPropertyClosure($action, $name, [$this, 'getSettable']))(...$params);
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

