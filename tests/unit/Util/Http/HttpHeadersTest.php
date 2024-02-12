<?php declare(strict_types=1);

namespace Lkrms\Tests\Http;

use Lkrms\Contract\ICollection;
use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Http\Catalog\HttpHeader;
use Lkrms\Http\Catalog\HttpHeaderGroup;
use Lkrms\Http\OAuth2\AccessToken;
use Lkrms\Http\HttpHeaders;
use Lkrms\Support\Catalog\MimeType;
use Lkrms\Tests\TestCase;
use Lkrms\Utility\Arr;
use LogicException;

final class HttpHeadersTest extends TestCase
{
    /**
     * @dataProvider constructProvider
     *
     * @param array<string,string[]>|string $expected
     * @param string[]|null $expectedLines
     * @param array<string,string[]|string> $items
     */
    public function testConstruct($expected, ?array $expectedLines, array $items): void
    {
        $this->maybeExpectException($expected);
        $headers = new HttpHeaders($items);
        $this->assertSame($expected, $headers->all());
        $this->assertSame($expectedLines, $headers->getLines());
    }

    /**
     * @return array<array{array<string,string[]>|string,string[]|null,array<string,string[]|string>}>
     */
    public static function constructProvider(): array
    {
        return [
            [
                ['foo' => ['bar', 'bar']],
                ['foo: bar', 'Foo: bar'],
                ['foo' => 'bar', 'Foo' => 'bar'],
            ],
            [
                InvalidArgumentException::class . ',Invalid header name: foo bar',
                null,
                ['foo bar' => 'qux'],
            ],
            [
                InvalidArgumentException::class . ",Invalid header value: bar\v",
                null,
                ['foo' => "bar\v"],
            ],
        ];
    }

    public function testEmpty(): void
    {
        $headers = new HttpHeaders();
        $this->assertSame([], $headers->all());
    }

    /**
     * @dataProvider addLineProvider
     *
     * @param array<string,string[]>|string $expected
     * @param string[] $lines
     */
    public function testAddLine($expected, array $lines, bool $strict = false, ?bool $trailers = null): void
    {
        $this->maybeExpectException($expected);
        $headers = new HttpHeaders();
        foreach ($lines as $line) {
            $headers = $headers->addLine($line, $strict);
        }

        $this->assertSame(
            $expected,
            $trailers === null
                ? $headers->getHeaders()
                : ($trailers
                    ? $headers->trailers()->getHeaders()
                    : $headers->withoutTrailers()->getHeaders())
        );
    }

    /**
     * @return array<string,array{array<string,string[]>|string,string[],2?:bool,3?:bool|null}>
     */
    public static function addLineProvider(): array
    {
        return [
            'no CRLF' => [
                ['A' => ['1', '2', '3']],
                ['A : 1', 'A:2', 'a: 3'],
            ],
            'space after delimiter' => [
                ['A' => ['1', '2', '3']],
                ["A: 1\r\n", "A:2\r\n", "a: 3\r\n"],
            ],
            'space before delimiter' => [
                ['A' => ['1', '2', '3']],
                ["A : 1\r\n", "A:2\r\n", "a: 3\r\n"],
            ],
            'preserve case of first with same name' => [
                ['a' => ['1', '2', '3']],
                ["a: 1\r\n", "A:2\r\n", "A: 3\r\n"],
            ],
            'multiple' => [
                ['foo' => ['bar'], 'baz' => ['qux']],
                ["foo: bar\r\n", "baz: qux\r\n"],
            ],
            'trailing whitespace' => [
                ['foo' => ['bar'], 'baz' => ['qux']],
                ["foo: bar \r\n", "baz: qux\t\r\n"],
            ],
            'trailing header #1' => [
                ['foo' => ['bar'], 'baz' => ['qux']],
                ["foo: bar\r\n", "\r\n", "baz: qux\r\n"],
            ],
            'trailing header #2' => [
                ['foo' => ['bar']],
                ["foo: bar\r\n", "\r\n", "baz: qux\r\n"],
                false,
                false,
            ],
            'trailing header #3' => [
                ['baz' => ['qux']],
                ["foo: bar\r\n", "\r\n", "baz: qux\r\n"],
                false,
                true,
            ],
            'trailing header #4' => [
                ['baz' => ['qux']],
                ["foo: bar\r\n", "\r\n", "baz: qux\r\n", "\r\n"],
                false,
                true,
            ],
            'folded line (SP)' => [
                ['long' => ['line1 line2']],
                ["long: line1\r\n", " line2\r\n"],
            ],
            'folded line (HTAB)' => [
                ['long' => ['line1 line2']],
                ["long: line1\r\n", "\tline2\r\n"],
            ],
            'folded line + multiple spaces' => [
                ['long' => ['line1 line2']],
                ["long: line1\r\n", "    line2\r\n"],
            ],
            'folded line + carried whitespace' => [
                ['long' => ["line1  line2\t line3"]],
                ["long: line1 \r\n", " line2\t\r\n", " line3\r\n"],
            ],
            'invalid header' => [
                [],
                ["nope\r\n"],
            ],
            '[strict] no CRLF' => [
                InvalidArgumentException::class . ',HTTP header field must end with CRLF',
                ['A : 1', 'A:2', 'a: 3'],
                true,
            ],
            '[strict] space after delimiter' => [
                ['A' => ['1', '2', '3']],
                ["A: 1\r\n", "A:2\r\n", "a: 3\r\n"],
                true,
            ],
            '[strict] space before delimiter' => [
                InvalidArgumentException::class . ',Invalid HTTP header field: A : 1',
                ["A : 1\r\n", "A:2\r\n", "a: 3\r\n"],
                true,
            ],
            '[strict] preserve case of first with same name' => [
                ['a' => ['1', '2', '3']],
                ["a: 1\r\n", "A:2\r\n", "A: 3\r\n"],
                true,
            ],
            '[strict] multiple' => [
                ['foo' => ['bar'], 'baz' => ['qux']],
                ["foo: bar\r\n", "baz: qux\r\n"],
                true,
            ],
            '[strict] trailing whitespace' => [
                ['foo' => ['bar'], 'baz' => ['qux']],
                ["foo: bar \r\n", "baz: qux\t\r\n"],
                true,
            ],
            '[strict] trailing header #1' => [
                ['foo' => ['bar'], 'baz' => ['qux']],
                ["foo: bar\r\n", "\r\n", "baz: qux\r\n"],
                true,
            ],
            '[strict] trailing header #2' => [
                ['foo' => ['bar']],
                ["foo: bar\r\n", "\r\n", "baz: qux\r\n"],
                true,
                false,
            ],
            '[strict] trailing header #3' => [
                ['baz' => ['qux']],
                ["foo: bar\r\n", "\r\n", "baz: qux\r\n"],
                true,
                true,
            ],
            '[strict] trailing header #4' => [
                InvalidArgumentException::class . ',HTTP message cannot have empty header after body',
                ["foo: bar\r\n", "\r\n", "baz: qux\r\n", "\r\n"],
                true,
                true,
            ],
            '[strict] folded line (SP)' => [
                ['long' => ['line1 line2']],
                ["long: line1\r\n", " line2\r\n"],
                true,
            ],
            '[strict] folded line (HTAB)' => [
                ['long' => ['line1 line2']],
                ["long: line1\r\n", "\tline2\r\n"],
                true,
            ],
            '[strict] folded line + multiple spaces' => [
                ['long' => ['line1 line2']],
                ["long: line1\r\n", "    line2\r\n"],
                true,
            ],
            '[strict] folded line + carried whitespace' => [
                ['long' => ["line1  line2\t line3"]],
                ["long: line1 \r\n", " line2\t\r\n", " line3\r\n"],
                true,
            ],
            '[strict] invalid header' => [
                InvalidArgumentException::class . ',Invalid HTTP header field: nope',
                ["nope\r\n"],
                true,
            ],
        ];
    }

    public function testImmutability(): void
    {
        $a = new HttpHeaders();
        $b = $a->set(HttpHeader::CONTENT_TYPE, MimeType::TEXT);
        $c = $b->set(HttpHeader::CONTENT_TYPE, MimeType::JSON);
        $d = $c->set(HttpHeader::CONTENT_TYPE, MimeType::JSON);
        $this->assertNotSame($b, $a);
        $this->assertNotSame($c, $b);
        $this->assertSame($d, $c);

        $a = new HttpHeaders();
        $b = $a->add(HttpHeader::PREFER, ['respond-async', 'wait=5', 'handling=lenient', 'task_priority=2']);
        $c = $b->add(HttpHeader::PREFER, 'odata.maxpagesize=100');
        $d = $c->set(HttpHeader::PREFER, [
            'respond-async',
            'wait=5',
            'handling=lenient',
            'task_priority=2',
            'odata.maxpagesize=100',
        ]);
        $e = $d->set(HttpHeader::PREFER, [
            'respond-async',
            'wait=5',
            'handling=lenient',
            'task_priority=2',
            'odata.maxpagesize=50',
        ]);
        $f = $e->merge(clone $d, false);
        $g = $e->merge(clone $e, false);
        $h = $g->merge([HttpHeader::PREFER => [
            'respond-async',
            'wait=5',
            'handling=lenient',
            'task_priority=2',
            'odata.maxpagesize=50',
        ]], false);
        $i = $h->merge([HttpHeader::PREFER => [
            'respond-async',
            'wait=5',
            'handling=lenient',
            'task_priority=2',
            'odata.maxpagesize=100',
        ]], false);
        $j = $i->unset(HttpHeader::PREFER);
        $this->assertNotSame($b, $a);
        $this->assertNotSame($c, $b);
        $this->assertSame($d, $c);
        $this->assertNotSame($e, $d);
        $this->assertNotSame($f, $e);
        $this->assertSame($g, $e);
        $this->assertSame($h, $g);
        $this->assertNotSame($i, $h);
        $this->assertNotSame($j, $i);
        $this->assertCount(1, $g);
        $this->assertCount(0, $j);
    }

    public function testEmptyValue(): void
    {
        $this->assertSame(
            ['A' => ['']],
            (new HttpHeaders())->set('A', '')->getHeaders()
        );
    }

    public function testFilter(): void
    {
        $index = Arr::toIndex(Arr::lower(HttpHeaderGroup::SENSITIVE));
        $token = new AccessToken('foo.bar.baz', 'Bearer', time() + 3600);
        $headers = (new HttpHeaders())
            ->authorize($token)
            ->set(HttpHeader::ACCEPT, '*/*');
        $this->assertSame([
            'Authorization' => ['Bearer foo.bar.baz'],
            'Accept' => ['*/*'],
        ], $headers->getHeaders());
        $headers = $headers
            ->filter(
                fn(string $key) => !($index[$key] ?? false),
                ICollection::CALLBACK_USE_KEY
            );
        $this->assertSame([
            'Accept' => ['*/*'],
        ], $headers->getHeaders());
    }

    /**
     * @dataProvider onlyProvider
     *
     * @param array<string,string[]> $expected
     * @param array<string,string[]|string> $items
     * @param string[] $keys
     */
    public function testOnly(array $expected, array $items, array $keys): void
    {
        $headers = new HttpHeaders($items);
        $headers1 = $headers->only($keys);
        $headers2 = $headers1->only($keys);
        $this->assertNotSame($headers, $headers1);
        $this->assertInstanceOf(HttpHeaders::class, $headers1);
        $this->assertSame($expected, $headers1->all());
        $this->assertSame($headers1, $headers2);

        $index = Arr::toIndex($keys);
        $headers3 = $headers->onlyIn($index);
        $headers4 = $headers1->onlyIn($index);
        $this->assertNotSame($headers, $headers3);
        $this->assertNotSame($headers1, $headers3);
        $this->assertEquals($headers1, $headers3);
        $this->assertSame($expected, $headers3->all());
        $this->assertSame($headers1, $headers4);
    }

    /**
     * @return array<array{array<string,string[]>,array<string,string[]|string>,string[]}>
     */
    public static function onlyProvider(): array
    {
        return [
            [
                [
                    'foo' => ['bar'],
                ],
                [
                    'Foo' => 'bar',
                    'Baz' => 'qux',
                    'Abc' => 'def',
                ],
                [
                    'FOO',
                    'XYZ',
                ]
            ]
        ];
    }

    /**
     * @dataProvider exceptProvider
     *
     * @param array<string,string[]> $expected
     * @param array<string,string[]|string> $items
     * @param string[] $keys
     */
    public function testExcept(array $expected, array $items, array $keys): void
    {
        $headers = new HttpHeaders($items);
        $headers1 = $headers->except($keys);
        $headers2 = $headers1->except($keys);
        $this->assertNotSame($headers, $headers1);
        $this->assertInstanceOf(HttpHeaders::class, $headers1);
        $this->assertSame($expected, $headers1->all());
        $this->assertSame($headers1, $headers2);

        $index = Arr::toIndex($keys);
        $headers3 = $headers->exceptIn($index);
        $headers4 = $headers1->exceptIn($index);
        $this->assertNotSame($headers, $headers3);
        $this->assertNotSame($headers1, $headers3);
        $this->assertEquals($headers1, $headers3);
        $this->assertSame($expected, $headers3->all());
        $this->assertSame($headers1, $headers4);
    }

    /**
     * @return array<array{array<string,string[]>,array<string,string[]|string>,string[]}>
     */
    public static function exceptProvider(): array
    {
        return [
            [
                [
                    'baz' => ['qux'],
                    'abc' => ['def'],
                ],
                [
                    'Foo' => 'bar',
                    'Baz' => 'qux',
                    'Abc' => 'def',
                ],
                [
                    'FOO',
                    'XYZ',
                ]
            ]
        ];
    }

    public function testOffsetSet(): void
    {
        $headers = new HttpHeaders();
        $this->expectException(LogicException::class);
        $headers[HttpHeader::CONTENT_TYPE] = [MimeType::JSON];
    }

    public function testOffsetUnset(): void
    {
        $headers = new HttpHeaders();
        $this->expectException(LogicException::class);
        unset($headers[HttpHeader::CONTENT_TYPE]);
    }
}
