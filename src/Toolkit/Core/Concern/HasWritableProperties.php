<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Core\Contract\Writable;
use Salient\Core\Introspector;

/**
 * Implements Writable
 *
 * - If `_set<Property>()` is defined, it is called instead of assigning
 *   `$value` to `<Property>`.
 * - If `_unset<Property>()` is defined, it is called to unset `<Property>`
 *   instead of assigning `null`.
 * - The existence of `_set<Property>()` makes `<Property>` writable, regardless
 *   of {@see Writable::getWritableProperties()}'s return value.
 *
 * @api
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
        Introspector::get(static::class)->getPropertyActionClosure($name, $action)($this, ...$params);
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
