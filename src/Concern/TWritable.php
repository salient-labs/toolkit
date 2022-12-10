<?php declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Implements IWritable (mostly)
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
    use HasIntrospector;

    abstract public static function getWritable(): array;

    private function setProperty(string $action, string $name, ...$params)
    {
        return $this->introspector()->getPropertyActionClosure($name, $action)($this, ...$params);
    }

    final public function __set(string $name, $value): void
    {
        $this->setProperty('set', $name, $value);
    }

    final public function __unset(string $name): void
    {
        $this->setProperty('unset', $name);
    }
}
