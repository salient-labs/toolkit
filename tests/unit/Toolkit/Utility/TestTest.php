<?php declare(strict_types=1);

namespace Salient\Tests\Utility;

use Salient\Tests\TestCase;
use Salient\Utility\Test;
use stdClass;
use Stringable;

/**
 * @covers \Salient\Utility\Test
 */
final class TestTest extends TestCase
{
    /**
     * @dataProvider isBooleanProvider
     *
     * @param mixed $value
     */
    public function testIsBoolean(bool $expected, $value): void
    {
        $this->assertSame($expected, Test::isBoolean($value));
    }

    /**
     * @return array<array{bool,mixed}>
     */
    public static function isBooleanProvider(): array
    {
        return [
            [false, -1],
            [false, ''],
            [false, 'f'],
            [false, 'nah'],
            [false, 'ok'],
            [false, 't'],
            [false, 'truth'],
            [false, 'ye'],
            [false, 'yeah'],
            [false, 0],
            [false, 1],
            [false, null],
            [true, ' true '],
            [true, '0'],
            [true, '1'],
            [true, 'disable'],
            [true, 'disabled'],
            [true, 'enable'],
            [true, 'enabled'],
            [true, 'false'],
            [true, 'n'],
            [true, 'N'],
            [true, 'no'],
            [true, 'off'],
            [true, 'OFF'],
            [true, 'on'],
            [true, 'ON'],
            [true, 'true'],
            [true, 'y'],
            [true, 'Y'],
            [true, 'yes'],
            [true, "false\n"],
            [true, false],
            [true, true],
        ];
    }

    /**
     * @dataProvider isIntegerProvider
     *
     * @param mixed $value
     */
    public function testIsInteger(bool $expected, $value): void
    {
        $this->assertSame($expected, Test::isInteger($value));
    }

    /**
     * @return array<array{bool,mixed}>
     */
    public static function isIntegerProvider(): array
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
            [true, ' 71 '],
            [true, '-0'],
            [true, '-1'],
            [true, '-71'],
            [true, '+0'],
            [true, '+1'],
            [true, '0'],
            [true, '1'],
            [true, '71'],
            [true, "+0\n"],
            [true, 0],
            [true, 1],
            [true, 71],
        ];
    }

    /**
     * @dataProvider isFloatProvider
     *
     * @param mixed $value
     */
    public function testIsFloat(bool $expected, $value): void
    {
        $this->assertSame($expected, Test::isFloat($value));
    }

    /**
     * @return array<array{bool,mixed}>
     */
    public static function isFloatProvider(): array
    {
        return [
            [false, -1],
            [false, '-0.1a'],
            [false, '-0'],
            [false, '-0a'],
            [false, '-1'],
            [false, '-a'],
            [false, '+0.1a'],
            [false, '+0'],
            [false, '+0a'],
            [false, '+1'],
            [false, '+a'],
            [false, '0.1a'],
            [false, '0'],
            [false, '0a'],
            [false, '1'],
            [false, 'a'],
            [false, 0],
            [false, 1],
            [true, -1.23],
            [true, ' +0.0 '],
            [true, '-0.0'],
            [true, '-1.2e3'],
            [true, '-1.23'],
            [true, '+0.0'],
            [true, '+1.2e3'],
            [true, '+1.23'],
            [true, '0.0'],
            [true, '1.2e3'],
            [true, '1.23'],
            [true, "+1.2e3\n"],
            [true, 0.0],
            [true, 1.23],
        ];
    }

    /**
     * @dataProvider isNumericKeyProvider
     *
     * @param mixed $value
     */
    public function testIsNumericKey(bool $expected, $value): void
    {
        if (is_float($value)) {
            $level = error_reporting();
            error_reporting($level & ~\E_DEPRECATED);
        }

        $this->assertSame($expected, is_int(array_key_first([$value => 'foo'])));
        $this->assertSame($expected, Test::isNumericKey($value));

        if (isset($level)) {
            error_reporting($level);
        }
    }

    /**
     * @return array<array{bool,mixed}>
     */
    public static function isNumericKeyProvider(): array
    {
        return [
            [false, ' 123'],
            [false, '-0'],
            [false, ''],
            [false, '+0'],
            [false, '+1'],
            [false, '0755'],
            [false, 'abc'],
            [false, "0\n"],
            [false, null],
            [true, '-1'],
            [true, '0'],
            [true, '123'],
            [true, 12.34],
            [true, 123],
            [true, false],
            [true, true],
        ];
    }

    /**
     * @dataProvider isDateStringProvider
     *
     * @param mixed $value
     */
    public function testIsDateString(bool $expected, $value): void
    {
        $this->assertSame($expected, Test::isDateString($value));
    }

    /**
     * @return array<array{bool,mixed}>
     */
    public static function isDateStringProvider(): array
    {
        return [
            [false, ''],
            [false, '0'],
            [false, '1'],
            [false, 'not a date'],
            [false, 0],
            [false, 1],
            [true, '@1711152000'],
            [true, '+1 hour'],
            [true, '2022-01-01 00:00:00 UTC'],
            [true, '2022-01-01 11:00:00'],
            [true, '2022-01-01'],
            [true, '2022-01-01T00:00:00Z'],
            [true, '2022-01-01T11:00:00+11:00'],
            [true, 'now'],
            [true, 'yesterday'],
        ];
    }

    /**
     * @dataProvider isStringableProvider
     *
     * @param mixed $value
     */
    public function testIsStringable(bool $expected, $value): void
    {
        $this->assertSame($expected, Test::isStringable($value));
    }

    /**
     * @return array<array{bool,mixed}>
     */
    public static function isStringableProvider(): array
    {
        return [
            [false, 123],
            [false, new stdClass()],
            [true, 'string'],
            [true, new class { public function __toString() { return 'string'; } }],
            [true, new class implements Stringable { public function __toString() { return 'string'; } }],
        ];
    }

    /**
     * @dataProvider isBetweenProvider
     *
     * @template T of int|float
     *
     * @param T $value
     * @param T $min
     * @param T $max
     */
    public function testIsBetween(bool $expected, $value, $min, $max): void
    {
        $this->assertSame($expected, Test::isBetween($value, $min, $max));
    }

    /**
     * @return array<array{bool,int,int,int}|array{bool,float,float,float}>
     */
    public static function isBetweenProvider(): array
    {
        return [
            [false, 0, 1, 10],
            [false, 11, 1, 10],
            [false, 5.0e-3, 1.0e3, 1.0e6],
            [true, 0, 0, 0],
            [true, 1, 1, 1],
            [true, 1, 1, 10],
            [true, 5, 1, 10],
            [true, 10, 1, 10],
            [true, 0.0, 0.0, 0.0],
            [true, 0.1, 0.1, 0.1],
            [true, 1.0e3, 1.0e3, 1.0e6],
            [true, 1.0e6, 1.0e3, 1.0e6],
            [true, 5.0e5, 1.0e3, 1.0e6],
        ];
    }

    /**
     * @dataProvider isBuiltinTypeProvider
     */
    public function testIsBuiltinType(bool $expected, string $value): void
    {
        $this->assertSame($expected, Test::isBuiltinType($value));
    }

    /**
     * @return array<array{bool,string}>
     */
    public static function isBuiltinTypeProvider(): array
    {
        return [
            [false, ''],
            [false, 'not a type'],
            [true, 'array'],
        ];
    }

    /**
     * @dataProvider isFqcnProvider
     *
     * @param mixed $value
     */
    public function testIsFqcn(bool $expected, $value): void
    {
        $this->assertSame($expected, Test::isFqcn($value));
    }

    /**
     * @return array<array{bool,mixed}>
     */
    public static function isFqcnProvider(): array
    {
        return [
            [false, ''],
            [false, 'not a class'],
            [false, "AcmeSyncProvider\n"],
            [true, 'AcmeSyncProvider'],
            [true, '\AcmeSyncProvider'],
            [true, 'Acme\Sync\Provider'],
            [true, '\Acme\Sync\Provider'],
        ];
    }
}
