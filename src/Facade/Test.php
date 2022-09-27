<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Countable;
use Iterator;
use IteratorAggregate;
use Lkrms\Concept\Facade;
use Lkrms\Utility\Tests;

/**
 * A facade for \Lkrms\Utility\Tests
 *
 * @method static Tests load() Load and return an instance of the underlying Tests class
 * @method static Tests getInstance() Return the underlying Tests instance
 * @method static bool isLoaded() Return true if an underlying Tests instance has been loaded
 * @method static void unload() Clear the underlying Tests instance
 * @method static bool areSameFile(string $path1, string $path2) Return true if two paths exist and refer to the same file (see {@see Tests::areSameFile()})
 * @method static bool classImplements(object|string $class, string $interface) Return true if an object or class implements the given interface (see {@see Tests::classImplements()})
 * @method static bool isAbsolutePath(string $path) Return true for absolute paths (see {@see Tests::isAbsolutePath()})
 * @method static bool isAssociativeArray(mixed $value, bool $allowEmpty = false) Return true for arrays with one or more string keys (see {@see Tests::isAssociativeArray()})
 * @method static bool isEmpty(array|Countable|Iterator|IteratorAggregate $value) Return true if an array, iterable or Countable is empty (see {@see Tests::isEmpty()})
 * @method static bool isFlagSet(int $value, int $flag, ?int $mask = null) Check if a flag is set in a bitmask (see {@see Tests::isFlagSet()})
 * @method static bool isIndexedArray(mixed $value, bool $allowEmpty = false) Return true for arrays with no string keys (see {@see Tests::isIndexedArray()})
 * @method static bool isIntValue(mixed $value) Return true for integers and integer strings (see {@see Tests::isIntValue()})
 * @method static bool isListArray(mixed $value, bool $allowEmpty = false) Return true for arrays with consecutive integer keys numbered from 0 (see {@see Tests::isListArray()})
 * @method static bool isOneFlagSet(int $value, int $mask) Check if only one flag is set in a bitmask (see {@see Tests::isOneFlagSet()})
 * @method static bool isPhpReservedWord(string $name) Return true for PHP reserved words (see {@see Tests::isPhpReservedWord()})
 *
 * @uses Tests
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\Tests' --generate='Lkrms\Facade\Test'
 */
final class Test extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Tests::class;
    }
}