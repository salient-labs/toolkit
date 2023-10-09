<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Utility\Test;
use DateTimeImmutable;
use DateTimeInterface;

final class TestTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider isBoolValueProvider
     *
     * @param mixed $value
     */
    public function testIsBoolValue(bool $expected, $value): void
    {
        $this->assertSame($expected, Test::isBoolValue($value));
    }

    /**
     * @return array<array{bool,mixed}>
     */
    public static function isBoolValueProvider(): array
    {
        return [
            [false, -1],
            [false, ''],
            [false, 'nah'],
            [false, 'ok'],
            [false, 'truth'],
            [false, 'ye'],
            [false, 'yeah'],
            [false, 0],
            [false, 1],
            [false, null],
            [true, '0'],
            [true, '1'],
            [true, 'disable'],
            [true, 'disabled'],
            [true, 'enable'],
            [true, 'enabled'],
            [true, 'f'],
            [true, 'false'],
            [true, 'n'],
            [true, 'N'],
            [true, 'no'],
            [true, 'off'],
            [true, 'OFF'],
            [true, 'on'],
            [true, 'ON'],
            [true, 't'],
            [true, 'true'],
            [true, 'y'],
            [true, 'Y'],
            [true, 'yes'],
            [true, false],
            [true, true],
        ];
    }

    /**
     * @dataProvider isIntValueProvider
     *
     * @param mixed $value
     */
    public function testIsIntValue(bool $expected, $value): void
    {
        $this->assertSame($expected, Test::isIntValue($value));
    }

    /**
     * @return array<array{bool,mixed}>
     */
    public static function isIntValueProvider(): array
    {
        return [
            [false, -0.1],
            [false, '-0.1'],
            [false, '-0a'],
            [false, '-a'],
            [false, '+0.1'],
            [false, '+0a'],
            [false, '+a'],
            [false, '0.1'],
            [false, '0a'],
            [false, 'a'],
            [false, 0.1],
            [false, false],
            [false, null],
            [false, true],
            [true, -1],
            [true, -71],
            [true, '-0'],
            [true, '-1'],
            [true, '-71'],
            [true, '+0'],
            [true, '+1'],
            [true, '0'],
            [true, '1'],
            [true, '71'],
            [true, 0],
            [true, 1],
            [true, 71],
        ];
    }

    /**
     * @dataProvider isListArrayProvider
     *
     * @param mixed $value
     */
    public function testIsListArray(bool $expected, $value, bool $allowEmpty = false): void
    {
        $this->assertSame($expected, Test::isListArray($value, $allowEmpty));
    }

    /**
     * @return array<array{bool,mixed,2?:bool}>
     */
    public static function isListArrayProvider(): array
    {
        return [
            [false, null],
            [false, []],
            [true, [], true],
            [true, ['a', 'b', 'c']],
            [false, [1 => 'a', 2 => 'b', 3 => 'c']],
            [false, ['a', 'b', 5 => 'c']],
            [false, ['a', 3 => 'b', 2 => 'c']],
            [false, ['a' => 'alpha', 2 => 'b', 3 => 'c']],
            [false, ['a' => 'alpha', 'b' => 'bravo', 'c' => 'charlie']],
        ];
    }

    /**
     * @dataProvider isAssociativeArrayProvider
     *
     * @param mixed $value
     */
    public function testIsAssociativeArray(bool $expected, $value, bool $allowEmpty = false): void
    {
        $this->assertSame($expected, Test::isAssociativeArray($value, $allowEmpty));
    }

    /**
     * @return array<array{bool,mixed,2?:bool}>
     */
    public static function isAssociativeArrayProvider(): array
    {
        return [
            [false, null],
            [false, []],
            [true, [], true],
            [false, ['a', 'b', 'c']],
            [false, [1 => 'a', 2 => 'b', 3 => 'c']],
            [false, ['a', 'b', 5 => 'c']],
            [false, ['a', 3 => 'b', 2 => 'c']],
            [true, ['a' => 'alpha', 2 => 'b', 3 => 'c']],
            [true, ['a' => 'alpha', 'b' => 'bravo', 'c' => 'charlie']],
        ];
    }

    /**
     * @dataProvider isIndexedArrayProvider
     *
     * @param mixed $value
     */
    public function testIsIndexedArray(bool $expected, $value, bool $allowEmpty = false): void
    {
        $this->assertSame($expected, Test::isIndexedArray($value, $allowEmpty));
    }

    /**
     * @return array<array{bool,mixed,2?:bool}>
     */
    public static function isIndexedArrayProvider(): array
    {
        return [
            [false, null],
            [false, []],
            [true, [], true],
            [true, ['a', 'b', 'c']],
            [true, [1 => 'a', 2 => 'b', 3 => 'c']],
            [true, ['a', 'b', 5 => 'c']],
            [true, ['a', 3 => 'b', 2 => 'c']],
            [false, ['a' => 'alpha', 2 => 'b', 3 => 'c']],
            [false, ['a' => 'alpha', 'b' => 'bravo', 'c' => 'charlie']],
        ];
    }

    /**
     * @dataProvider isArrayOfArrayKeyProvider
     *
     * @param mixed $value
     */
    public function testIsArrayOfArrayKey($value, bool $expected, bool $expectedIfAllowEmpty): void
    {
        $this->assertSame($expected, Test::isArrayOfArrayKey($value));
        $this->assertSame($expectedIfAllowEmpty, Test::isArrayOfArrayKey($value, true));
    }

    /**
     * @return array<string,array{mixed,bool,bool}>
     */
    public static function isArrayOfArrayKeyProvider(): array
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
    public function testIsArrayOfInt($value, bool $expected, bool $expectedIfAllowEmpty): void
    {
        $this->assertSame($expected, Test::isArrayOfInt($value));
        $this->assertSame($expectedIfAllowEmpty, Test::isArrayOfInt($value, true));
    }

    /**
     * @return array<string,array{mixed,bool,bool}>
     */
    public static function isArrayOfIntProvider(): array
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
    public function testIsArrayOfString($value, bool $expected, bool $expectedIfAllowEmpty): void
    {
        $this->assertSame($expected, Test::isArrayOfString($value));
        $this->assertSame($expectedIfAllowEmpty, Test::isArrayOfString($value, true));
    }

    /**
     * @return array<string,array{mixed,bool,bool}>
     */
    public static function isArrayOfStringProvider(): array
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
    public function testIsArrayOfValue($value, $itemValue, bool $strict, bool $expected, bool $expectedIfAllowEmpty): void
    {
        $this->assertSame($expected, Test::isArrayOfValue($value, $itemValue, $strict));
        $this->assertSame($expectedIfAllowEmpty, Test::isArrayOfValue($value, $itemValue, $strict, true));
    }

    /**
     * @return array<string,array{mixed,mixed,bool,bool,bool}>
     */
    public static function isArrayOfValueProvider(): array
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

    /**
     * @dataProvider isArrayOfProvider
     *
     * @param mixed $value
     */
    public function testIsArrayOf($value, string $class, bool $expected, bool $expectedIfAllowEmpty): void
    {
        $this->assertSame($expected, Test::isArrayOf($value, $class));
        $this->assertSame($expectedIfAllowEmpty, Test::isArrayOf($value, $class, true));
    }

    /**
     * @return array<string,array{mixed,string,bool,bool}>
     */
    public static function isArrayOfProvider(): array
    {
        $now = fn() => new DateTimeImmutable();

        return [
            'null' => [null, DateTimeInterface::class, false, false],
            'int' => [0, DateTimeInterface::class, false, false],
            'string' => ['a', DateTimeInterface::class, false, false],
            'empty' => [[], DateTimeInterface::class, false, true],
            'ints' => [[0, 1], DateTimeInterface::class, false, false],
            'strings' => [['a', 'b'], DateTimeInterface::class, false, false],
            'datetimes' => [[$now(), $now()], DateTimeInterface::class, true, true],
            'mixed #1' => [[0, 'a', $now()], DateTimeInterface::class, false, false],
            'mixed #2' => [[0, 1, true], DateTimeInterface::class, false, false],
            'mixed #3' => [[0, 1, null], DateTimeInterface::class, false, false],
            'mixed #4' => [['a', 'b', true], DateTimeInterface::class, false, false],
            'mixed #5' => [['a', 'b', null], DateTimeInterface::class, false, false],
            'mixed #6' => [[$now, $now, true], DateTimeInterface::class, false, false],
            'mixed #7' => [[$now, $now, null], DateTimeInterface::class, false, false],
        ];
    }
}
