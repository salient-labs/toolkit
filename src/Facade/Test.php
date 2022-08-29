<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Countable;
use Iterator;
use IteratorAggregate;
use Lkrms\Concept\Facade;
use Lkrms\Utility\Tests;

/**
 * A facade for Tests
 *
 * @method static bool areSameFile(string $path1, string $path2) Return true if two paths exist and refer to the same file
 * @method static bool classImplements(object|string $class, string $interface) Return true if an object or class implements the given interface
 * @method static bool isAbsolutePath(string $path) Return true for absolute paths
 * @method static bool isAssociativeArray(mixed $value, bool $allowEmpty = false) Return true for arrays with one or more string keys
 * @method static bool isEmpty(array|Countable|Iterator|IteratorAggregate $value) Return true if an array, iterable or Countable is empty
 * @method static bool isFlagSet(int $value, int $flag, ?int $mask = null) Check if a flag is set in a bitmask
 * @method static bool isIndexedArray(mixed $value, bool $allowEmpty = false) Return true for arrays with no string keys
 * @method static bool isIntValue(mixed $value) Return true for integers and integer strings
 * @method static bool isListArray(mixed $value, bool $allowEmpty = false) Return true for arrays with consecutive integer keys numbered from 0
 * @method static bool isOneFlagSet(int $value, int $mask) Check if only one flag is set in a bitmask
 * @method static bool isPhpReservedWord(string $name) Return true for PHP reserved words
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
