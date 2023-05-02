<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Utility\Test;

final class TestTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider isArrayOfArrayKeyProvider
     *
     * @param mixed $value
     */
    public function testIsArrayOfArrayKey($value, bool $expected, bool $expectedIfAllowEmpty)
    {
        $this->assertSame($expected, Test::isArrayOfArrayKey($value));
        $this->assertSame($expectedIfAllowEmpty, Test::isArrayOfArrayKey($value, true));
    }

    public static function isArrayOfArrayKeyProvider()
    {
        return [
            'null' => [null, false, false],
            'int' => [0, false, false],
            'string' => ['a', false, false],
            'empty' => [[], false, true],
            'ints' => [[0, 1], true, true],
            'strings' => [['a', 'b'], true, true],
            'mixed #1' => [[0, 'a'], false, false],
            'mixed #2' => [[0, 1, true], false, false],
            'mixed #3' => [[0, 1, null], false, false],
            'mixed #4' => [['a', 'b', true], false, false],
            'mixed #5' => [['a', 'b', null], false, false],
        ];
    }

    /**
     * @dataProvider isArrayOfIntProvider
     *
     * @param mixed $value
     */
    public function testIsArrayOfInt($value, bool $expected, bool $expectedIfAllowEmpty)
    {
        $this->assertSame($expected, Test::isArrayOfInt($value));
        $this->assertSame($expectedIfAllowEmpty, Test::isArrayOfInt($value, true));
    }

    public static function isArrayOfIntProvider()
    {
        return [
            'null' => [null, false, false],
            'int' => [0, false, false],
            'string' => ['a', false, false],
            'empty' => [[], false, true],
            'ints' => [[0, 1], true, true],
            'strings' => [['a', 'b'], false, false],
            'mixed #1' => [[0, 'a'], false, false],
            'mixed #2' => [[0, 1, true], false, false],
            'mixed #3' => [[0, 1, null], false, false],
            'mixed #4' => [['a', 'b', true], false, false],
            'mixed #5' => [['a', 'b', null], false, false],
        ];
    }

    /**
     * @dataProvider isArrayOfStringProvider
     *
     * @param mixed $value
     */
    public function testIsArrayOfString($value, bool $expected, bool $expectedIfAllowEmpty)
    {
        $this->assertSame($expected, Test::isArrayOfString($value));
        $this->assertSame($expectedIfAllowEmpty, Test::isArrayOfString($value, true));
    }

    public static function isArrayOfStringProvider()
    {
        return [
            'null' => [null, false, false],
            'int' => [0, false, false],
            'string' => ['a', false, false],
            'empty' => [[], false, true],
            'ints' => [[0, 1], false, false],
            'strings' => [['a', 'b'], true, true],
            'mixed #1' => [[0, 'a'], false, false],
            'mixed #2' => [[0, 1, true], false, false],
            'mixed #3' => [[0, 1, null], false, false],
            'mixed #4' => [['a', 'b', true], false, false],
            'mixed #5' => [['a', 'b', null], false, false],
        ];
    }

    /**
     * @dataProvider isArrayOfValueProvider
     *
     * @param mixed $value
     * @param mixed $itemValue
     */
    public function testIsArrayOfValue($value, $itemValue, bool $strict, bool $expected, bool $expectedIfAllowEmpty)
    {
        $this->assertSame($expected, Test::isArrayOfValue($value, $itemValue, $strict));
        $this->assertSame($expectedIfAllowEmpty, Test::isArrayOfValue($value, $itemValue, $strict, true));
    }

    public static function isArrayOfValueProvider()
    {
        return [
            'null' => [null, null, false, false, false],
            'bool' => [true, true, false, false, false],
            'int' => [0, 0, false, false, false],
            'string' => ['a', 'a', false, false, false],
            'empty' => [[], null, true, false, true],
            'bools #1' => [[false, false], false, true, true, true],
            'bools #2' => [[true, true], true, true, true, true],
            'strict bools' => [[0, false], false, true, false, false],
            'relaxed bools' => [[0, false], false, false, true, true],
            'mixed bools #1' => [[true, false], false, true, false, false],
            'mixed bools #2' => [[false, true], false, true, false, false],
            'mixed bools #3' => [[true, false], false, false, false, false],
            'mixed bools #4' => [[false, true], false, false, false, false],
            'ints #1' => [[1, '1'], 1, true, false, false],
            'ints #2' => [[1, '1'], 1, false, true, true],
            'ints #3' => [[1, 1], 1, true, true, true],
            'strings #1' => [['a', 'a'], 'a', true, true, true],
            'strings #2' => [['a', 'a'], 0, false, PHP_VERSION_ID < 80000, PHP_VERSION_ID < 80000],
            'strings #3' => [['a', 'b'], 'a', false, false, false],
            'nulls' => [[null, null], null, true, true, true],
            'mixed #1' => [['', 0, [], false, null], null, true, false, false],
            'mixed #2' => [['', 0, [], false, null], null, false, true, true],
        ];
    }
}
