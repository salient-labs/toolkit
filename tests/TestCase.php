<?php declare(strict_types=1);

namespace Lkrms\Tests;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Asserts that `$array` contains every key-value pair in `$subArray`, in
     * the same order
     *
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
     * Runs a command and returns its output, failing a test if there are any
     * errors
     *
     */
    public function shellExec(string $command): string
    {
        if (exec($command, $output, $exitCode) === false) {
            $this->fail("Command failed: $command");
        }
        if ($exitCode) {
            $this->fail("Command failed with exit code $exitCode: $command");
        }

        return implode("\n", $output);
    }
}
