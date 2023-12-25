<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Tests\TestCase;
use Lkrms\Utility\Get;
use stdClass;

final class GetTest extends TestCase
{
    /**
     * @dataProvider basenameProvider
     */
    public function testBasename(string $expected, string $class, string ...$suffixes): void
    {
        $this->assertSame($expected, Get::basename($class, ...$suffixes));
    }

    /**
     * @return array<string[]>
     */
    public static function basenameProvider(): array
    {
        return [
            [
                'AcmeSyncProvider',
                'Acme\Sync\Provider\AcmeSyncProvider',
            ],
            [
                'AcmeSyncProvider',
                'Acme\Sync\Provider\AcmeSyncProvider',
                'Sync',
            ],
            [
                'Acme',
                'Acme\Sync\Provider\AcmeSyncProvider',
                'SyncProvider',
                'Provider',
            ],
            [
                'AcmeSync',
                'Acme\Sync\Provider\AcmeSyncProvider',
                'Provider',
                'SyncProvider',
            ],
            [
                'AcmeSync',
                'Acme\Sync\Provider\AcmeSyncProvider',
                'Provider',
                'SyncProvider',
            ],
            [
                'AcmeSyncProvider',
                'AcmeSyncProvider',
            ],
            [
                'AcmeSyncProvider',
                'AcmeSyncProvider',
                'AcmeSyncProvider',
            ],
            [
                'Acme',
                'AcmeSyncProvider',
                'AcmeSyncProvider',
                'SyncProvider',
            ],
        ];
    }

    /**
     * @dataProvider namespaceProvider
     */
    public function testNamespace(string $expected, string $class): void
    {
        $this->assertSame($expected, Get::namespace($class));
    }

    /**
     * @return array<string[]>
     */
    public static function namespaceProvider(): array
    {
        return [
            [
                'Acme\Sync\Provider',
                'Acme\Sync\Provider\AcmeSyncProvider',
            ],
            [
                'Acme\Sync\Provider',
                '\Acme\Sync\Provider\AcmeSyncProvider',
            ],
            [
                '',
                'AcmeSyncProvider',
            ],
            [
                '',
                '\AcmeSyncProvider',
            ],
        ];
    }

    /**
     * @dataProvider typeProvider
     *
     * @param mixed $value
     */
    public function testType(string $expected, $value): void
    {
        $this->assertSame($expected, Get::type($value));
    }

    /**
     * @return array<array{string,mixed}>
     */
    public static function typeProvider(): array
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
            [stdClass::class, new stdClass()],
            ['class@anonymous', new class {}],
        ];
    }

    public function testTypeWithResource(): void
    {
        $f = fopen(__FILE__, 'r');
        $this->assertSame('resource (stream)', Get::type($f));
        fclose($f);
    }

    /**
     * @dataProvider eolProvider
     */
    public function testEol(?string $expected, string $string): void
    {
        $this->assertSame($expected, Get::eol($string));
    }

    /**
     * @return array<string,array{string|null,string}>
     */
    public static function eolProvider(): array
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
