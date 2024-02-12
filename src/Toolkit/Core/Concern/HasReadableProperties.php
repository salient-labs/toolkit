<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Lkrms\Support\Introspector as IS;
use Salient\Core\Contract\Readable;

/**
 * Implements Readable
 *
 * - If `_get<Property>()` is defined, `__get` will use its return value instead
 *   of returning the value of `<Property>`.
 * - If `_isset<Property>()` is defined, `__isset` will use its return value
 *   instead of returning the value of `isset(<Property>)`.
 * - The existence of `_get<Property>()` implies that `<Property>` is readable,
 *   regardless of {@see HasReadableProperties::getReadableProperties()}'s
 *   return value.
 *
 * @see Readable
 */
trait HasReadableProperties
{
    public static function getReadableProperties(): array
    {
        return [];
    }

    /**
     * @return mixed
     */
    private function getProperty(string $action, string $name)
    {
        return IS::get(static::class)->getPropertyActionClosure($name, $action)($this);
    }

    /**
     * @return mixed
     */
    final public function __get(string $name)
    {
        return $this->getProperty('get', $name);
    }

    final public function __isset(string $name): bool
    {
        return (bool) $this->getProperty('isset', $name);
    }
}
