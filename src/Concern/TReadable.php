<?php declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Implements IReadable (mostly)
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
    use HasIntrospector;

    abstract public static function getReadable(): array;

    private function getProperty(string $action, string $name)
    {
        return $this->introspector()->getPropertyActionClosure($name, $action)($this);
    }

    final public function __get(string $name)
    {
        return $this->getProperty('get', $name);
    }

    final public function __isset(string $name): bool
    {
        return (bool) $this->getProperty('isset', $name);
    }
}
