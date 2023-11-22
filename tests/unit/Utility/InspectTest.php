<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Utility\Inspect;

final class InspectTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider getTypeProvider
     *
     * @param mixed $value
     */
    public function testGetType(string $expected, $value): void
    {
        $this->assertSame($expected, Inspect::getType($value));
    }

    /**
     * @return array<array{string,mixed}>
     */
    public static function getTypeProvider(): array
    {
        $f = fopen(__FILE__, 'r');
        fclose($f);

        return [
            ['null', null],
            ['bool', true],
            ['bool', false],
            ['int', 0],
            ['int', 1],
            ['float', 0.0],
            ['float', 3.14],
            ['string', ''],
            ['string', 'text'],
            ['string', '0'],
            ['array', []],
            ['array', ['foo', 'bar']],
            ['resource (closed)', $f],
            [\stdClass::class, new \stdClass()],
            ['class@anonymous', new class {}],
        ];
    }

    public function testGetTypeWithResource(): void
    {
        $f = fopen(__FILE__, 'r');
        $this->assertSame('resource (stream)', Inspect::getType($f));
        fclose($f);
    }

    /**
     * @dataProvider getEolProvider
     */
    public function testGetEol(?string $expected, string $string): void
    {
        $this->assertSame($expected, Inspect::getEol($string));
    }

    /**
     * @return array<string,array{string|null,string}>
     */
    public static function getEolProvider(): array
    {
        return [
            'empty string' => [null, ''],
            'no newlines' => [null, 'line'],
            'LF newlines' => ["\n", "line1\nline2\n"],
            'CRLF newlines' => ["\r\n", "line1\r\nline2\r\n"],
            'CR newlines' => ["\r", "line1\rline2\r"],
        ];
    }
}
