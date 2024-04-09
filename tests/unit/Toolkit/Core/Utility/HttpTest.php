<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Salient\Contract\Core\MimeType;
use Salient\Core\Utility\Http;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Core\Utility\Http
 */
final class HttpTest extends TestCase
{
    /**
     * @dataProvider mediaTypeIsProvider
     */
    public function testMediaTypeIs(bool $expected, string $type, string $mimeType): void
    {
        $this->assertSame($expected, Http::mediaTypeIs($type, $mimeType));
    }

    /**
     * @return array<array{bool,string,string}>
     */
    public static function mediaTypeIsProvider(): array
    {
        return [
            [true, 'application/jwk-set+json', 'application/jwk-set'],
            [true, 'application/jwk-set+json', MimeType::JSON],
            [true, 'application/xml', MimeType::XML],
            [true, 'APPLICATION/XML', MimeType::XML],
            [false, 'application/xml-dtd', MimeType::XML],
            [true, 'application/rss+xml', MimeType::XML],
            [true, 'text/xml', MimeType::XML],
            [true, 'Text/HTML;Charset="utf-8"', MimeType::HTML],
            [false, 'Text/HTML;Charset="utf-8"', MimeType::TEXT],
        ];
    }
}
