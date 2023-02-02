<?php declare(strict_types=1);

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
 * @method static bool areSameFile(string $path1, string $path2) Return true if two paths exist and refer to the same file
 * @method static bool classImplements(object|string $class, string $interface) Return true if an object or class implements the given interface
 * @method static bool firstExistingDirectoryIsWritable(string $dir) Return true if a directory exists and is writable, or doesn't exist but can be created
 * @method static bool isAbsolutePath(string $path) Return true for absolute paths
 * @method static bool isArrayOf($value, string $class, bool $strict = false, bool $allowEmpty = false, bool $requireList = false, bool $requireIndexed = false) Return true for arrays of a class (see {@see Tests::isArrayOf()})
 * @method static bool isArrayOfArray($value, bool $allowEmpty = false, bool $requireList = false, bool $requireIndexed = false) Return true for array[] (see {@see Tests::isArrayOfArray()})
 * @method static bool isArrayOfIntOrString($value, bool $allowEmpty = false, bool $requireList = false, bool $requireIndexed = false) Return true for string[] and int[] (see {@see Tests::isArrayOfIntOrString()})
 * @method static bool isAssociativeArray(mixed $value, bool $allowEmpty = false) Return true for arrays with one or more string keys
 * @method static bool isBetween(int|float $value, int|float $min, int|float $max) Return true for numbers within a range
 * @method static bool isEmpty(array|Countable|Iterator|IteratorAggregate $value) Return true if an array, iterable or Countable is empty (see {@see Tests::isEmpty()})
 * @method static bool isIndexedArray(mixed $value, bool $allowEmpty = false) Return true for arrays with no string keys
 * @method static bool isIntValue(mixed $value) Return true for integers and integer strings
 * @method static bool isListArray(mixed $value, bool $allowEmpty = false) Return true for arrays with consecutive integer keys numbered from 0
 * @method static bool isPharUrl(string $path) Return true if a path starts with 'phar://'
 * @method static bool isPhpReservedWord(string $name) Return true for PHP reserved words (see {@see Tests::isPhpReservedWord()})
 *
 * @uses Tests
 * @extends Facade<Tests>
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Utility\Tests' 'Lkrms\Facade\Test'
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
