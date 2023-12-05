<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Utility\Str;

final class StrTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider coalesceProvider
     */
    public function testCoalesce(?string $expected, ?string $string, ?string ...$strings): void
    {
        $this->assertSame($expected, Str::coalesce($string, ...$strings));
    }

    /**
     * @return array<array<string|null>>
     */
    public static function coalesceProvider(): array
    {
        return [
            [
                null,
                null,
            ],
            [
                '',
                '',
            ],
            [
                null,
                '',
                null,
            ],
            [
                '',
                null,
                '',
            ],
            [
                'a',
                '',
                null,
                'a',
                null,
            ],
            [
                '0',
                '0',
                '1',
                null,
            ],
        ];
    }
}
