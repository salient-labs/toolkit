<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Tests;

/**
 * A facade for \Lkrms\Utility\Tests
 *
 * @method static Tests load() Load and return an instance of the underlying Tests class
 * @method static Tests getInstance() Get the underlying Tests instance
 * @method static bool isLoaded() True if an underlying Tests instance has been loaded
 * @method static void unload() Clear the underlying Tests instance
 * @method static bool areSameFile(string $path1, string $path2) True if two paths exist and refer to the same file
 * @method static bool classImplements(object|class-string $class, class-string $interface) True if an object or class implements an interface
 * @method static bool firstExistingDirectoryIsWritable(string $dir) True if a directory exists and is writable, or doesn't exist but can be created
 * @method static bool isAbsolutePath(string $path) True if $path is an absolute path (see {@see Tests::isAbsolutePath()})
 * @method static bool isArrayOf($value, string $class, bool $allowEmpty = false) True if $value is an array of instances of $class
 * @method static bool isArrayOfIntOrString(mixed $value, bool $allowEmpty = false) True if $value is a string[] or int[]
 * @method static bool isAssociativeArray(mixed $value, bool $allowEmpty = false) True if $value is an array with one or more string keys
 * @method static bool isBetween(int|float $value, int|float $min, int|float $max) True if $value is a number within a range (see {@see Tests::isBetween()})
 * @method static bool isBoolValue(mixed $value) True if $value is a boolean or boolean string (see {@see Tests::isBoolValue()})
 * @method static bool isIndexedArray(mixed $value, bool $allowEmpty = false) True if $value is an array with no string keys
 * @method static bool isIntValue(mixed $value) True if $value is an integer or integer string
 * @method static bool isListArray(mixed $value, bool $allowEmpty = false) True if $value is an array with consecutive integer keys numbered from 0
 * @method static bool isPharUrl(string $path) True if $path starts with 'phar://'
 * @method static bool isPhpReservedWord(string $value) True if $value is a PHP reserved word (see {@see Tests::isPhpReservedWord()})
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
