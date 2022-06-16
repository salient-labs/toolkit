<?php

declare(strict_types=1);

namespace Lkrms\Concern\Partial;

// - PHP 8.0 added the `mixed` type
// - PHP 8.1 enforced return type compatibility with built-in interfaces
// - `Iterator` and `ArrayAccess` have methods with return type `mixed`
// - PHP 9.0 is expected to ignore the interim ReturnTypeWillChange attribute
//
// TL;DR: some of PHP's built-in interfaces can't be implemented in
// backward-compatible code without some of it being version-specific
if (PHP_VERSION_ID < 80000)
{
    /**
     * @internal
     */
    trait TCollection
    {
        /**
         * @return mixed|false
         */
        final public function current()
        {
            return current($this->Items);
        }

        /**
         * @return int|string|null
         */
        final public function key()
        {
            return key($this->Items);
        }

        /**
         * @return mixed
         */
        final public function offsetGet($offset)
        {
            return $this->Items[$offset];
        }
    }
}
else
{
    /**
     * @internal
     */
    trait TCollection
    {
        // Partial implementation of `Iterator`:

        /**
         * @return mixed|false
         */
        final public function current(): mixed
        {
            return current($this->Items);
        }

        /**
         * @return int|string|null
         */
        final public function key(): mixed
        {
            return key($this->Items);
        }

        // Partial implementation of `ArrayAccess`:

        /**
         * @return mixed
         */
        final public function offsetGet($offset): mixed
        {
            return $this->Items[$offset];
        }
    }
}
