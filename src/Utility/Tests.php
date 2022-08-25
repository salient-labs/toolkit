<?php

declare(strict_types=1);

namespace Lkrms\Utility;

use Countable;
use Iterator;
use IteratorAggregate;

/**
 * Perform a true/false test on a value
 *
 */
final class Tests
{
    /**
     * Return true for integers and integer strings
     *
     * @param mixed $value
     * @return bool
     */
    public function isIntValue($value): bool
    {
        return (is_int($value) ||
            (is_string($value) && preg_match('/^[0-9]+$/', $value)));
    }

    /**
     * Check if a flag is set in a bitmask
     *
     * If `$mask` is not set, returns `true` if bits set in `$flag` are also set
     * in `$value`.
     *
     * If `$mask` is set, returns `true` if masked bits in `$flag` and `$value`
     * have the same state.
     *
     * @param int $value The bitmask being checked.
     * @param int $flag The value of the flag.
     * @param null|int $mask The mask being applied to `$value` and `$flag`.
     * @return bool
     */
    public function isFlagSet(int $value, int $flag, ?int $mask = null): bool
    {
        return ($value & ($mask ?? $flag)) === $flag;
    }

    /**
     * Check if only one flag is set in a bitmask
     *
     * Returns `true` if exactly one of the masked bits in `$value` is set.
     *
     * @param int $value The bitmask being checked.
     * @param int $mask The mask being applied to `$value`.
     * @return bool
     */
    public function isOneFlagSet(int $value, int $mask): bool
    {
        return substr_count(decbin($value & $mask), "1") === 1;
    }

    /**
     * Return true for arrays with consecutive integer keys numbered from 0
     *
     * @param mixed $value
     * @param bool $allowEmpty
     * @return bool
     */
    public function isListArray($value, bool $allowEmpty = false): bool
    {
        return is_array($value) &&
            (empty($value) ? $allowEmpty : array_keys($value) === range(0, count($value) - 1));
    }

    /**
     * Return true for arrays with one or more string keys
     *
     * @param mixed $value
     * @param bool $allowEmpty
     * @return bool
     */
    public function isAssociativeArray($value, bool $allowEmpty = false): bool
    {
        if (is_array($value))
        {
            if (empty($value))
            {
                return $allowEmpty;
            }

            foreach (array_keys($value) as $key)
            {
                if (is_string($key))
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Return true for arrays with no string keys
     *
     * @param mixed $value
     * @param bool $allowEmpty
     * @return bool
     */
    public function isIndexedArray($value, bool $allowEmpty = false): bool
    {
        return is_array($value) &&
            (empty($value) ? $allowEmpty : !$this->isAssociativeArray($value));
    }

    /**
     * Return true for absolute paths
     *
     * @param string $path
     * @return bool
     */
    public function isAbsolutePath(string $path): bool
    {
        return (bool)preg_match('/^(\\/|\\\\|[a-z]:\\\\)/i', $path);
    }

    /**
     * Return true if an object or class implements the given interface
     *
     * @param object|string $class
     * @param string $interface
     * @return bool
     */
    public function classImplements($class, string $interface): bool
    {
        return in_array($interface, class_implements($class) ?: []);
    }

    /**
     * Return true if two paths exist and refer to the same file
     *
     * @param string $path1
     * @param string $path2
     * @return bool
     */
    public function areSameFile(string $path1, string $path2): bool
    {
        return file_exists($path1) && file_exists($path2) &&
            is_int($inode = fileinode($path1)) &&
            fileinode($path2) === $inode;
    }

    /**
     * Return true if an array, iterable or Countable is empty
     *
     * Bear in mind that if `$value` is an empty `iterable`, calling this method
     * will implicitly close it.
     *
     * @param array|Countable|Iterator|IteratorAggregate $value
     * @return bool
     */
    public function isEmpty($value): bool
    {
        if (is_array($value) || $value instanceof Countable)
        {
            return count($value) === 0;
        }
        elseif ($value instanceof Iterator ||
            ($value instanceof IteratorAggregate &&
                ($value = $value->getIterator()) instanceof Iterator))
        {
            return !$value->valid();
        }
        else
        {
            return false;
        }
    }

    /**
     * Return true for PHP reserved words
     *
     * @link https://www.php.net/manual/en/reserved.php
     */
    public function isPhpReservedWord(string $name): bool
    {
        return in_array(strtolower($name), [
            "array", "bool", "callable", "enum", "false",
            "float", "int", "iterable", "mixed", "never",
            "null", "numeric", "object", "parent", "resource",
            "self", "static", "string", "true", "void",
        ]);
    }
}
