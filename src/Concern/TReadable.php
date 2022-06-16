<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Support\ClosureBuilder;

/**
 * Implements IReadable to read properties that have not been declared or are
 * not visible in the current scope
 *
 * Override {@see TReadable::getReadable()} to provide access to `protected`
 * variables via `__get` and `__isset`.
 *
 * The default is to deny `__get` and `__isset` for all properties.
 *
 * - If `_get<Property>()` is defined, `__get` will use its return value instead
 *   of returning the value of `<Property>`.
 * - If `_isset<Property>()` is defined, `__isset` will use its return value
 *   instead of returning the value of `isset(<Property>)`.
 * - The existence of `_get<Property>()` implies that `<Property>` is readable,
 *   regardless of {@see TReadable::getReadable()}'s return value.
 *
 * @see \Lkrms\Contract\IReadable
 */
trait TReadable
{
    /**
     * Return a list of readable protected properties
     *
     * To make all `protected` properties readable, return `["*"]`.
     *
     * @return string[]
     */
    public static function getReadable(): array
    {
        return static::getGettable();
    }

    /**
     * @deprecated Rename to getReadable
     */
    public static function getGettable(): array
    {
        return [];
    }

    private function getProperty(string $action, string $name)
    {
        return (ClosureBuilder::get(static::class)->getPropertyActionClosure($name, $action))($this);
    }

    final public function __get(string $name)
    {
        return $this->getProperty("get", $name);
    }

    final public function __isset(string $name): bool
    {
        return (bool)$this->getProperty("isset", $name);
    }
}
