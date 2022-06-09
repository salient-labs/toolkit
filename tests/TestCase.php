<?php

declare(strict_types=1);

namespace Lkrms\Tests;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    public function assertArrayHasSubArray(array $subArray, array $array, string $message = '')
    {
        $this->assertEquals(
            $subArray,
            array_intersect_key($array, $subArray),
            $message
        );
    }

    public function assertArrayHasSignature(array $keys, array $array, string $message = '')
    {
        $stub = array_combine($keys, array_fill(0, count($keys), "<value>")) ?: [];
        $this->assertEquals(
            array_intersect_key($array + $stub, $stub),
            $array,
            $message
        );
    }

    public function assertArrayHasSubArrayAndKeys(array $subArray, array $keys, array $array, string $message = '')
    {
        $this->assertArrayHasSubArray($subArray, $array, $message);
        $this->assertArrayHasSignature(
            array_keys($subArray + array_flip($keys)),
            $array,
            $message
        );
    }
}
