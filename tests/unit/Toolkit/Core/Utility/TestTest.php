<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Lkrms\Tests\TestCase;
use Salient\Core\Utility\Test;

final class TestTest extends TestCase
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
            [false, 'f'],
            [true, 'false'],
            [true, 'n'],
            [true, 'N'],
            [true, 'no'],
            [true, 'off'],
            [true, 'OFF'],
            [true, 'on'],
            [true, 'ON'],
            [false, 't'],
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
}
