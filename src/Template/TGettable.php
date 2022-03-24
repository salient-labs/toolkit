<?php

declare(strict_types=1);

namespace Lkrms\Template;

use Closure;

/**
 * A basic implementation of __get and __isset
 *
 * Override {@see TGettable::getGettable()} to limit access to `protected`
 * variables via `__get` and `__isset`.
 *
 * The default is to allow `__get` and `__isset` for all `protected` properties.
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
     * To make all `protected` properties gettable, return
     * {@see IAccessible::ALLOW_ALL_PROTECTED}.
     *
     * @return null|string[]
     */
    public function getGettable(): ?array
    {
        return IAccessible::ALLOW_ALL_PROTECTED;
    }

    private function getProperty(string $action, string $name)
    {
        return ($this->getPropertyClosure($action, $name, [$this, 'getGettable']))();
    }

    final public function __get(string $name)
    {
        return $this->getProperty("get", $name);
    }

    final public function __isset(string $name): bool
    {
        return (bool)$this->getProperty("isset", $name);
    }

    /**
     * @var array<string,array<string,PropertyResolver>>
     */
    private static $GettableResolver = [];

    /**
     * @var array<string,array<string,array<string,Closure>>>
     */
    private static $GettableClosure = [];

    private function getPropertyResolver(string $action, callable $allowed): PropertyResolver
    {
        $arr = & self::$GettableResolver;

        if (!isset($arr[ static::class]))
        {
            $arr[ static::class] = [];
        }

        if (!isset($arr[ static::class][$action]))
        {
            $arr[ static::class][$action] = new PropertyResolver(static::class, $action, ($allowed)());
        }

        return $arr[ static::class][$action];
    }

    private function getPropertyClosure(string $action, string $name, callable $allowed): Closure
    {
        $arr = & self::$GettableClosure;

        if (!isset($arr[ static::class]))
        {
            $arr[ static::class] = [];
        }

        if (!isset($arr[ static::class][$action]))
        {
            $arr[ static::class][$action] = [];
        }

        if (!isset($arr[ static::class][$action][$name]))
        {
            $arr[ static::class][$action][$name] = $this->getPropertyResolver($action, $allowed)->getClosure($name);
        }

        return ($arr[ static::class][$action][$name])->BindTo($this, $this);
    }
}

