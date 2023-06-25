<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Method calls and property actions are delegated to a receiving object
 *
 * @template TDelegate of object
 * @mixin TDelegate
 */
interface HasDelegate
{
    /**
     * Get an instance with a given delegate
     *
     * Returns a "sending object" that delegates calls and property actions to a
     * "receiving object".
     *
     * @template T of object
     * @param T $delegate
     * @return self<T>
     */
    public static function withDelegate(object $delegate);

    /**
     * Get the object's delegate
     *
     * @return TDelegate
     */
    public function getDelegate(): object;

    /**
     * Call a method on the delegate
     *
     * @param mixed[] $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments);

    /**
     * Set the value of a property of the delegate
     *
     * @param mixed $value
     */
    public function __set(string $name, $value): void;

    /**
     * Get the value of a property of the delegate
     *
     * @return mixed
     */
    public function __get(string $name);

    /**
     * True if a property of the delegate is set
     *
     */
    public function __isset(string $name): bool;

    /**
     * Unset a property of the delegate
     *
     */
    public function __unset(string $name): void;
}
