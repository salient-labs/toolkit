<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Lkrms\Support\Introspector as IS;
use Salient\Core\Contract\Writable;

/**
 * Implements Writable
 *
 * - If `_set<Property>($value)` is defined, it will be called instead of
 *   assigning `$value` to `<Property>`.
 * - If `_unset<Property>()` is defined, it will be called to unset `<Property>`
 *   instead of assigning `null`.
 * - The existence of `_set<Property>()` implies that `<Property>` is writable,
 *   regardless of {@see HasWritableProperties::getWritableProperties()}'s
 *   return value.
 *
 * @see Writable
 */
trait HasWritableProperties
{
    public static function getWritableProperties(): array
    {
        return [];
    }

    /**
     * @param mixed ...$params
     */
    private function setProperty(string $action, string $name, ...$params): void
    {
        IS::get(static::class)->getPropertyActionClosure($name, $action)($this, ...$params);
    }

    /**
     * @param mixed $value
     */
    final public function __set(string $name, $value): void
    {
        $this->setProperty('set', $name, $value);
    }

    final public function __unset(string $name): void
    {
        $this->setProperty('unset', $name);
    }
}
