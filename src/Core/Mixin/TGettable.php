<?php

declare(strict_types=1);

namespace Lkrms\Core\Mixin;

use Lkrms\Core\ClosureBuilder;

/**
 * Implements IGettable to provide a basic implementation of __get and __isset
 *
 * Override {@see TGettable::getGettable()} to allow access to `protected`
 * variables via `__get` and `__isset`.
 *
 * The default is to deny `__get` and `__isset` for all properties.
 *
 * - If `_get<Property>()` is defined, `__get` will use its return value instead
 *   of returning the value of `<Property>`.
 * - If `_isset<Property>()` is defined, `__isset` will use its return value
 *   instead of returning the value of `isset(<Property>)`.
 * - The existence of `_get<Property>()` implies that `<Property>` is gettable,
 *   regardless of {@see TGettable::getGettable()}'s return value.
 *
 * @package Lkrms
 * @see IGettable
 */
trait TGettable
{
    /**
     * Return a list of gettable protected properties
     *
     * To make all `protected` properties gettable, return `["*"]`.
     *
     * @return string[]
     */
    public static function getGettable(): array
    {
        return [];
    }

    private function getProperty(string $action, string $name)
    {
        return (ClosureBuilder::getFor(static::class)->getPropertyActionClosure($name, $action))($this);
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
