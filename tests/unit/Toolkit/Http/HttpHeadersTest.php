<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Collection\Collection;
use Salient\Contract\Collection\CollectionInterface;
use Salient\Contract\Core\MimeType;
use Salient\Contract\Http\HttpHeader;
use Salient\Contract\Http\HttpHeaderGroup;
use Salient\Http\Exception\InvalidHeaderException;
use Salient\Http\OAuth2\AccessToken;
use Salient\Http\HttpHeaders;
use Salient\Tests\TestCase;
use Salient\Utility\Arr;
use ArrayIterator;
use InvalidArgumentException;
use LogicException;

/**
 * @covers \Salient\Http\HttpHeaders
 */
final class HttpHeadersTest extends TestCase
{
    /**
     * @dataProvider constructProvider
     *
     * @param array<string,string[]>|string $expected
     * @param string[]|null $expectedLines
     * @param array<string,string[]|string> $items
     */
    public function testConstructor($expected, ?array $expectedLines, array $items): void
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
                ['host' => ['example.com'], 'qux' => ['quux'], 'foo' => ['bar']],
                ['qux: quux', 'Foo: bar', 'Host: example.com'],
                ['qux' => 'quux', 'Foo' => 'bar', 'Host' => 'example.com'],
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
            [
                [],
                [],
                [],
            ]
        ];
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
            'invalid line folding' => [
                [],
                [" line2\r\n"],
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
            '[strict] invalid line folding' => [
                InvalidArgumentException::class . ',Invalid line folding:  line2',
                [" line2\r\n"],
                true,
            ],
            '[strict] invalid header' => [
                InvalidArgumentException::class . ',Invalid HTTP header field: nope',
                ["nope\r\n"],
                true,
            ],
        ];
    }

    public function testHasLastLine(): void
    {
        $headers = new HttpHeaders();
        $this->assertFalse($headers->hasLastLine());
        $headers = $headers->addLine("foo: bar\r\n");
        $this->assertFalse($headers->hasLastLine());
        $headers = $headers->addLine("\r\n");
        $this->assertTrue($headers->hasLastLine());
    }

    public function testHostHeaderIsFirst(): void
    {
        $headers = [
            new HttpHeaders(['Foo' => 'bar', 'Host' => 'example.com']),
            (new HttpHeaders())->addLine("Foo: bar\r\n")->addLine("Host: example.com\r\n"),
            (new HttpHeaders())->set('Foo', 'bar')->set('Host', 'example.com'),
        ];

        foreach ($headers as $headers) {
            $this->assertSame(
                ['host' => ['example.com'], 'foo' => ['bar']],
                $headers->all(),
            );
            $this->assertSame(
                ['host' => 'example.com', 'foo' => 'bar'],
                $headers->getHeaderLines(),
            );
            $this->assertSame(
                ['Foo: bar', 'Host: example.com'],
                $headers->getLines(),
            );
        }
    }

    /**
     * @dataProvider invalidHeaderProvider
     *
     * @param string[]|string $value
     */
    public function testInvalidHeaderArray(string $key, $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new HttpHeaders([$key => $value]));
    }

    /**
     * @dataProvider invalidHeaderProvider
     *
     * @param string[]|string $value
     */
    public function testInvalidHeaderArrayable(string $key, $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new HttpHeaders(new Collection([$key => $value])));
    }

    /**
     * @dataProvider invalidHeaderProvider
     *
     * @param string[]|string $value
     */
    public function testInvalidHeaderIterator(string $key, $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new HttpHeaders(new ArrayIterator([$key => $value])));
    }

    /**
     * @dataProvider invalidHeaderProvider
     *
     * @param string[]|string $value
     */
    public function testAddInvalidHeader(string $key, $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new HttpHeaders())->add($key, $value);
    }

    /**
     * @dataProvider invalidHeaderProvider
     *
     * @param string[]|string $value
     */
    public function testSetInvalidHeader(string $key, $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new HttpHeaders())->set($key, $value);
    }

    /**
     * @return array<string,array{string,string[]|string}>
     */
    public static function invalidHeaderProvider(): array
    {
        return [
            'no values' => ['foo', []],
            'name + LF' => ["foo\n", 'bar'],
            'name + CRLF' => ["foo\r\n", 'bar'],
            'value + LF' => ['foo', "bar\n"],
            'value + CRLF' => ['foo', "bar\r\n"],
            'invalid name' => ['(foo)', 'bar'],
            'invalid value' => ['foo', "\x7f"],
        ];
    }

    public function testGetHeader(): void
    {
        $headers = (new HttpHeaders())
            ->add('foo', ['qux', 'quux', 'quuux'])
            ->add('bar', ['baz'])
            ->add('qux', ['quux="comma,separated,value", quuux'])
            ->add('items', ['item1, item2', 'item3, item4,item5', 'item6,item7']);

        $this->assertTrue($headers->hasHeader('Foo'));
        $this->assertFalse($headers->hasHeader('Baz'));
        $this->assertSame(['qux', 'quux', 'quuux'], $headers->getHeader('Foo'));
        $this->assertSame([], $headers->getHeader('Baz'));
        $this->assertSame(['item1, item2', 'item3, item4,item5', 'item6,item7'], $headers->getHeader('Items'));
        $this->assertSame('qux, quux, quuux', $headers->getHeaderLine('Foo'));
        $this->assertSame('', $headers->getHeaderLine('Baz'));
        $this->assertSame('item1, item2, item3, item4,item5, item6,item7', $headers->getHeaderLine('Items'));
        $this->assertSame('qux', $headers->getFirstHeaderLine('Foo'));
        $this->assertSame('quuux', $headers->getLastHeaderLine('Foo'));
        $this->assertSame('baz', $headers->getOneHeaderLine('Bar'));
        $this->assertSame('quux="comma,separated,value"', $headers->getFirstHeaderLine('Qux'));
        $this->assertSame('quuux', $headers->getLastHeaderLine('Qux'));
        $this->assertSame('item1', $headers->getFirstHeaderLine('Items'));
        $this->assertSame('item7', $headers->getLastHeaderLine('Items'));

        $this->expectException(InvalidHeaderException::class);
        $this->expectExceptionMessage('HTTP header has more than one value: Qux');
        $headers->getOneHeaderLine('Qux');
    }

    public function testGetPreferences(): void
    {
        $this->assertSame([], (new HttpHeaders())->getPreferences());

        $headers = (new HttpHeaders())
            ->add(HttpHeader::PREFER, 'respond-async, WAIT=5; foo=bar, handling=lenient')
            ->add(HttpHeader::PREFER, 'wait=10; baz=qux')
            ->add(HttpHeader::PREFER, 'task_priority=2; baz="foo bar"')
            ->add(HttpHeader::PREFER, 'odata.maxpagesize=100');

        $this->assertSame([
            'respond-async' => ['value' => '', 'parameters' => []],
            'wait' => ['value' => '5', 'parameters' => ['foo' => 'bar']],
            'handling' => ['value' => 'lenient', 'parameters' => []],
            'task_priority' => ['value' => '2', 'parameters' => ['baz' => 'foo bar']],
            'odata.maxpagesize' => ['value' => '100', 'parameters' => []],
        ], $headers->getPreferences());
    }

    public function testMergePreferences(): void
    {
        $this->assertSame('', HttpHeaders::mergePreferences([]));

        $this->assertSame(
            'respond-async, WAIT=5; foo=bar, handling=lenient, task_priority=2; baz="foo bar", odata.maxpagesize=100',
            HttpHeaders::mergePreferences([
                'respond-async' => '',
                'WAIT' => ['value' => '5', 'parameters' => ['foo' => 'bar']],
                'wait' => '10',
                'handling' => ['value' => 'lenient'],
                'task_priority' => ['value' => '2', 'parameters' => ['baz' => 'foo bar']],
                'odata.maxpagesize' => ['value' => '100', 'parameters' => []],
            ]),
        );
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
        $k = $j->unset(HttpHeader::PREFER);
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
        $this->assertSame($j, $k);
    }

    public function testEmptyValue(): void
    {
        $headers = (new HttpHeaders(['foo' => 'bar', 'baz' => '']))->add('foo', '')->set('qux', '');
        $this->assertSame(['foo' => ['bar', ''], 'baz' => [''], 'qux' => ['']], $headers->getHeaders());
        $this->assertSame(['foo: bar', 'baz: ', 'foo: ', 'qux: '], $headers->getLines());
        $this->assertSame(['foo:bar', 'baz;', 'foo;', 'qux;'], $headers->getLines('%s:%s', '%s;'));
    }

    public function testCanonicalize(): void
    {
        $headers = $this->getHeaders()->set('host', 'example.com')->canonicalize();
        $this->assertSame([
            'host' => ['example.com'],
            'foo2' => ['*'],
            'foo' => ['bar', 'baz'],
            'abc' => ['def'],
            'qux' => ['quux'],
        ], $headers->all());
        $this->assertSame([
            'host' => ['example.com'],
            'Foo2' => ['*'],
            'foo' => ['bar', 'baz'],
            'abc' => ['def'],
            'qux' => ['quux'],
        ], $headers->getHeaders());
        $this->assertSame([
            'host: example.com',
            'Foo2: *',
            'foo: bar',
            'abc: def',
            'Foo: baz',
            'qux: quux',
        ], $headers->getLines());
    }

    public function testSort(): void
    {
        $headers = $this->getHeaders()->set('host', 'example.com')->sort();
        $this->assertSame([
            'host' => ['example.com'],
            'abc' => ['def'],
            'foo' => ['bar', 'baz'],
            'foo2' => ['*'],
            'qux' => ['quux'],
        ], $headers->all());
        $this->assertSame([
            'host' => ['example.com'],
            'abc' => ['def'],
            'foo' => ['bar', 'baz'],
            'Foo2' => ['*'],
            'qux' => ['quux'],
        ], $headers->getHeaders());
        $this->assertSame([
            'host: example.com',
            'Foo: baz',
            'Foo2: *',
            'abc: def',
            'foo: bar',
            'qux: quux',
        ], $headers->getLines());
    }

    public function testReverse(): void
    {
        $headers = $this->getHeaders()->set('host', 'example.com')->reverse();
        $this->assertSame([
            'host' => ['example.com'],
            'qux' => ['quux'],
            'abc' => ['def'],
            'foo' => ['bar', 'baz'],
            'foo2' => ['*'],
        ], $headers->all());
        $this->assertSame([
            'host' => ['example.com'],
            'qux' => ['quux'],
            'abc' => ['def'],
            'foo' => ['bar', 'baz'],
            'Foo2' => ['*'],
        ], $headers->getHeaders());
        $this->assertSame([
            'host: example.com',
            'qux: quux',
            'Foo: baz',
            'abc: def',
            'foo: bar',
            'Foo2: *',
        ], $headers->getLines());
    }

    private function getHeaders(): HttpHeaders
    {
        return new HttpHeaders([
            'Foo2' => '*',
            'foo' => 'bar',
            'abc' => 'def',
            'Foo' => 'baz',
            'qux' => 'quux',
        ]);
    }

    public function testFilter(): void
    {
        $index = Arr::toIndex(Arr::lower(HttpHeaderGroup::SENSITIVE));
        $token = new AccessToken('foo.bar.baz', 'Bearer', time() + 3600);
        $headers = (new HttpHeaders())
            ->authorize($token)
            ->set(HttpHeader::ACCEPT, '*/*')
            ->set('foo', ['bar', 'baz']);
        $this->assertSame(
            ['foo' => ['bar', 'baz']],
            $headers->filter(
                fn(array $values) => count($values) > 1,
            )->getHeaders()
        );
        $this->assertSame(
            ['Accept' => ['*/*'], 'foo' => ['bar', 'baz']],
            $headers->filter(
                fn(string $key) => !isset($index[$key]),
                CollectionInterface::CALLBACK_USE_KEY
            )->getHeaders()
        );
        $this->assertSame(
            ['Accept' => ['*/*']],
            $headers->filter(
                fn(array $map) => $map === ['accept', ['*/*']],
                CollectionInterface::CALLBACK_USE_BOTH
            )->getHeaders()
        );
    }

    public function testCompareItems(): void
    {
        $this->assertTrue((new HttpHeaders(['foo' => 'bar']))->hasValue(['bar']));
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
