<?php declare(strict_types=1);

namespace Lkrms\Tests;

use Lkrms\Utility\Pcre;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Asserts that an array contains every key-value pair in a sub-array, in
     * the same order
     *
     * @param mixed[] $subArray
     * @param mixed[] $array
     */
    public function assertArrayHasSubArray(array $subArray, array $array, string $message = ''): void
    {
        $this->assertSame(
            $subArray,
            array_intersect_key($array, $subArray),
            $message
        );
    }

    /**
     * Asserts that an array has the given keys in the given order
     *
     * @param array-key[] $keys
     * @param mixed[] $array
     */
    public function assertArrayHasSignature(array $keys, array $array, string $message = ''): void
    {
        // Improve diff readability by adding "<value>" where missing keys
        // should be
        $expected = array_combine($keys, array_map(fn($key) => $array[$key] ?? '<value>', $keys)) ?: [];
        $this->assertSame(
            $expected,
            $array,
            $message
        );
    }

    /**
     * Asserts that an array contains every key-value pair in a sub-array, in
     * the same order, followed by values with the given keys in the given order
     *
     * @param mixed[] $subArray
     * @param array-key[] $keys
     * @param mixed[] $array
     */
    public function assertArrayHasSubArrayAndKeys(array $subArray, array $keys, array $array, string $message = ''): void
    {
        $this->assertArrayHasSubArray($subArray, $array, $message);
        $this->assertArrayHasSignature(
            array_keys($subArray + array_flip($keys)),
            $array,
            $message
        );
    }

    /**
     * Get the path to the fixtures directory for a class
     */
    public function getFixturesPath(string $class): string
    {
        return dirname(__DIR__)
            . '/fixtures/'
            . Pcre::replace(
                ['/^Lkrms\\\\(?|Tests\\\\(.+)Test$|(.+))/', '/\\\\/'],
                ['$1', '/'],
                $class
            );
    }

    /**
     * Replace newlines in a string with PHP_EOL
     */
    public function newlinesToNative(?string $string): ?string
    {
        return
            $string === null
                ? null
                : str_replace("\n", PHP_EOL, $string);
    }

    /**
     * Replace directory separators in a string with DIRECTORY_SEPARATOR
     */
    public function directorySeparatorsToNative(?string $string): ?string
    {
        return
            $string === null
                ? null
                : str_replace('/', DIRECTORY_SEPARATOR, $string);
    }
}
