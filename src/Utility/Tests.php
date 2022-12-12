<?php declare(strict_types=1);

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
        return is_int($value) ||
            (is_string($value) && preg_match('/^[0-9]+$/', $value));
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
        if (is_array($value)) {
            if (empty($value)) {
                return $allowEmpty;
            }

            foreach (array_keys($value) as $key) {
                if (is_string($key)) {
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
     * Return true for string[] and int[]
     *
     * Returns `false` unless `$value` is an array where all values are integers
     * or where all values are strings.
     */
    public function isArrayOfIntOrString($value, bool $allowEmpty = false, bool $requireList = false, bool $requireIndexed = false): bool
    {
        return is_array($value) &&
            (empty($value) ? $allowEmpty
                : (count(array_filter($value, fn($item) => is_string($item))) === count($value) ||
                    count(array_filter($value, fn($item) => is_int($item))) === count($value)) &&
                (!($requireList || $requireIndexed) ||
                    ($requireList && $this->isListArray($value)) ||
                    ((!$requireList) && $this->isIndexedArray($value))));
    }

    /**
     * Return true for array[]
     *
     * Returns `false` unless `$value` is an array where all values are arrays.
     */
    public function isArrayOfArray($value, bool $allowEmpty = false, bool $requireList = false, bool $requireIndexed = false): bool
    {
        return is_array($value) &&
            (empty($value) ? $allowEmpty
                : empty(array_filter($value, fn($item) => !is_array($item))) &&
                (!($requireList || $requireIndexed) ||
                    ($requireList && $this->isListArray($value)) ||
                    ((!$requireList) && $this->isIndexedArray($value))));
    }

    /**
     * Return true for arrays of a class
     *
     * Returns `false` unless `$value` is an array where every element is an
     * instance of `$class`.
     *
     * @param bool $strict If `true`, subclasses of `$class` are not allowed in
     * `$value`.
     */
    public function isArrayOf($value, string $class, bool $strict = false, bool $allowEmpty = false, bool $requireList = false, bool $requireIndexed = false): bool
    {
        return is_array($value) &&
            (empty($value) ? $allowEmpty
                : empty(array_filter($value, $strict
                        ? fn($val) => !is_object($val) || strcasecmp(get_class($val), $class)
                        : fn($val) => !is_a($val, $class))) &&
                (!($requireList || $requireIndexed) ||
                    ($requireList && $this->isListArray($value)) ||
                    ((!$requireList) && $this->isIndexedArray($value))));
    }

    /**
     * Return true for numbers within a range
     *
     * @param int|float $value
     * @param int|float $min
     * @param int|float $max
     */
    public function isBetween($value, $min, $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    /**
     * Return true for absolute paths
     *
     * @param string $path
     * @return bool
     */
    public function isAbsolutePath(string $path): bool
    {
        return (bool) preg_match('/^(\/|\\\\|[a-z]:\\\\)/i', $path);
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
        if (is_array($value) || $value instanceof Countable) {
            return count($value) === 0;
        } elseif ($value instanceof Iterator ||
                ($value instanceof IteratorAggregate &&
                    ($value = $value->getIterator()) instanceof Iterator)) {
            return !$value->valid();
        } else {
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
            'array', 'bool', 'callable', 'enum', 'false',
            'float', 'int', 'iterable', 'mixed', 'never',
            'null', 'numeric', 'object', 'parent', 'resource',
            'self', 'static', 'string', 'true', 'void',
        ]);
    }
}
