<?php declare(strict_types=1);

namespace Lkrms\Tests;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Asserts that `$array` contains every key-value pair in `$subArray`, in
     * the same order
     *
     * @param mixed[] $subArray
     * @param mixed[] $array
     */
    public function assertArrayHasSubArray(array $subArray, array $array, string $message = '')
    {
        $this->assertSame(
            $subArray,
            array_intersect_key($array, $subArray),
            $message
        );
    }

    /**
     * Asserts that `array_keys($array)` is equal to `$keys`
     *
     * @param array<array-key> $keys
     * @param mixed[] $array
     */
    public function assertArrayHasSignature(array $keys, array $array, string $message = '')
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
     * Asserts that `$array` and `$subArray` are identical, except that `$array`
     * has additional values at subsequent `$keys`
     *
     * For the assertion to pass, `array_keys($array)` must equal
     * `array_keys($subArray + array_flip($keys))`
     *
     * @param mixed[] $subArray
     * @param array<array-key> $keys
     * @param mixed[] $array
     */
    public function assertArrayHasSubArrayAndKeys(array $subArray, array $keys, array $array, string $message = '')
    {
        $this->assertArrayHasSubArray($subArray, $array, $message);
        $this->assertArrayHasSignature(
            array_keys($subArray + array_flip($keys)),
            $array,
            $message
        );
    }

    /**
     * Replace newlines with PHP_EOL
     *
     */
    public function lineEndingsToNative(?string $string): ?string
    {
        return $string === null ? null : str_replace("\n", PHP_EOL, $string);
    }
}
