<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Support\ClosureBuilder;

/**
 * Implements IWritable to write properties that have not been declared or are
 * not visible in the current scope
 *
 * Override {@see TWritable::getWritable()} to provide access to `protected`
 * variables via `__set` and `__unset`.
 *
 * The default is to deny `__set` and `__unset` for all properties.
 *
 * - If `_set<Property>($value)` is defined, it will be called instead of
 *   assigning `$value` to `<Property>`.
 * - If `_unset<Property>()` is defined, it will be called to unset `<Property>`
 *   instead of assigning `null`.
 * - The existence of `_set<Property>()` implies that `<Property>` is writable,
 *   regardless of {@see TWritable::getWritable()}'s return value.
 *
 * @see \Lkrms\Contract\IWritable
 */
trait TWritable
{
    /**
     * Return a list of writable protected properties
     *
     * To make all `protected` properties writable, return `["*"]`.
     *
     * @return string[]
     */
    public static function getWritable(): array
    {
        return static::getSettable();
    }

    /**
     * @deprecated Rename to getWritable
     */
    public static function getSettable(): array
    {
        return [];
    }

    private function setProperty(string $action, string $name, ...$params)
    {
        return (ClosureBuilder::get(static::class)->getPropertyActionClosure($name, $action))($this, ...$params);
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
