<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Collection\Collection;
use Salient\Contract\Collection\CollectionInterface;
use Salient\Contract\Http\Exception\InvalidHeaderException;
use Salient\Contract\Http\HasHttpHeader;
use Salient\Contract\Http\HasHttpHeaders;
use Salient\Contract\Http\HasMediaType;
use Salient\Http\OAuth2\AccessToken;
use Salient\Http\Headers;
use Salient\Http\HttpUtil;
use Salient\Tests\TestCase;
use Salient\Utility\Arr;
use ArrayIterator;
use InvalidArgumentException;
use LogicException;

/**
 * @covers \Salient\Http\Headers
 * @covers \Salient\Http\HttpUtil
 */
final class HttpHeadersTest extends TestCase implements
    HasHttpHeader,
    HasHttpHeaders,
    HasMediaType
{
    /**
     * @dataProvider constructorProvider
     *
     * @param array<string,string[]>|string $expected
     * @param string[]|null $expectedLines
     * @param array<string,string[]|string> $items
     */
    public function testConstructor($expected, ?array $expectedLines, array $items): void
    {
        $this->maybeExpectException($expected);
        $headers = new Headers($items);
        $this->assertSame($expected, $headers->all());
        $this->assertSame($expectedLines, $headers->getLines());
    }

    /**
     * @return array<array{array<string,string[]>|string,string[]|null,array<string,string[]|string>}>
     */
    public static function constructorProvider(): array
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
        $headers = new Headers();
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
                InvalidHeaderException::class . ',Invalid HTTP header line folding:  line2',
                [" line2\r\n"],
            ],
            'invalid header' => [
                InvalidHeaderException::class . ',Invalid HTTP header field: nope',
                ["nope\r\n"],
            ],
            '[strict] no CRLF' => [
                InvalidHeaderException::class . ',HTTP header line must end with CRLF',
                ['A : 1', 'A:2', 'a: 3'],
                true,
            ],
            '[strict] space after delimiter' => [
                ['A' => ['1', '2', '3']],
                ["A: 1\r\n", "A:2\r\n", "a: 3\r\n"],
                true,
            ],
            '[strict] space before delimiter' => [
                InvalidHeaderException::class . ',Invalid HTTP header field: A : 1',
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
                InvalidHeaderException::class . ',HTTP message cannot have empty header line after body',
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
                InvalidHeaderException::class . ',Invalid HTTP header line folding:  line2',
                [" line2\r\n"],
                true,
            ],
            '[strict] invalid header' => [
                InvalidHeaderException::class . ',Invalid HTTP header field: nope',
                ["nope\r\n"],
                true,
            ],
        ];
    }

    public function testHasEmptyLine(): void
    {
        $headers = new Headers();
        $this->assertFalse($headers->hasEmptyLine());
        $headers = $headers->addLine("foo: bar\r\n");
        $this->assertFalse($headers->hasEmptyLine());
        $headers = $headers->addLine("\r\n");
        $this->assertTrue($headers->hasEmptyLine());
    }

    public function testHostHeaderIsFirst(): void
    {
        $headers = [
            new Headers(['Foo' => 'bar', 'Host' => 'example.com']),
            (new Headers())->addLine("Foo: bar\r\n")->addLine("Host: example.com\r\n"),
            (new Headers())->set('Foo', 'bar')->set('Host', 'example.com'),
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
        (new Headers([$key => $value]));
    }

    /**
     * @dataProvider invalidHeaderProvider
     *
     * @param string[]|string $value
     */
    public function testInvalidHeaderArrayable(string $key, $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Headers(new Collection([$key => $value])));
    }

    /**
     * @dataProvider invalidHeaderProvider
     *
     * @param string[]|string $value
     */
    public function testInvalidHeaderIterator(string $key, $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Headers(new ArrayIterator([$key => $value])));
    }

    /**
     * @dataProvider invalidHeaderProvider
     *
     * @param string[]|string $value
     */
    public function testAddInvalidHeader(string $key, $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Headers())->addValue($key, $value);
    }

    /**
     * @dataProvider invalidHeaderProvider
     *
     * @param string[]|string $value
     */
    public function testSetInvalidHeader(string $key, $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Headers())->set($key, $value);
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
        $headers = (new Headers())
            ->addValue('foo', ['qux', 'quux', 'quuux'])
            ->addValue('bar', ['baz'])
            ->addValue('qux', ['quux="comma,separated,value", quuux'])
            ->addValue('size1', ['100', '100'])
            ->addValue('size2', ['100', '101'])
            ->addValue('items', ['item1, item2', 'item3, item4,item5', 'item6,item7']);

        $this->assertTrue($headers->hasHeader('Foo'));
        $this->assertFalse($headers->hasHeader('Baz'));
        $this->assertSame(['qux', 'quux', 'quuux'], $headers->getHeader('Foo'));
        $this->assertSame([], $headers->getHeader('Baz'));
        $this->assertSame(['item1, item2', 'item3, item4,item5', 'item6,item7'], $headers->getHeader('Items'));
        $this->assertSame('qux, quux, quuux', $headers->getHeaderLine('Foo'));
        $this->assertSame('', $headers->getHeaderLine('Baz'));
        $this->assertSame('item1, item2, item3, item4,item5, item6,item7', $headers->getHeaderLine('Items'));
        $this->assertSame('qux', $headers->getFirstHeaderValue('Foo'));
        $this->assertSame('quuux', $headers->getLastHeaderValue('Foo'));
        $this->assertSame('baz', $headers->getOnlyHeaderValue('Bar'));
        $this->assertSame('100', $headers->getOnlyHeaderValue('Size1', true));
        $this->assertSame('quux="comma,separated,value"', $headers->getFirstHeaderValue('Qux'));
        $this->assertSame('quuux', $headers->getLastHeaderValue('Qux'));
        $this->assertSame('item1', $headers->getFirstHeaderValue('Items'));
        $this->assertSame('item7', $headers->getLastHeaderValue('Items'));

        $this->assertCallbackThrowsException(
            fn() => $headers->getOnlyHeaderValue('Size1'),
            InvalidHeaderException::class,
            'HTTP header has more than one value: Size1',
        );
        $this->assertCallbackThrowsException(
            fn() => $headers->getOnlyHeaderValue('Size2', true),
            InvalidHeaderException::class,
            'HTTP header has more than one value: Size2',
        );

        $this->expectException(InvalidHeaderException::class);
        $this->expectExceptionMessage('HTTP header has more than one value: Qux');
        $headers->getOnlyHeaderValue('Qux');
    }

    public function testGetPreferences(): void
    {
        $this->assertSame([], HttpUtil::getPreferences(new Headers()));

        $headers = (new Headers())
            ->addValue(self::HEADER_PREFER, 'respond-async, WAIT=5; foo=bar, handling=lenient')
            ->addValue(self::HEADER_PREFER, 'wait=10; baz=qux')
            ->addValue(self::HEADER_PREFER, 'task_priority=2; baz="foo bar"')
            ->addValue(self::HEADER_PREFER, 'odata.maxpagesize=100');

        $this->assertSame([
            'respond-async' => ['value' => '', 'parameters' => []],
            'wait' => ['value' => '5', 'parameters' => ['foo' => 'bar']],
            'handling' => ['value' => 'lenient', 'parameters' => []],
            'task_priority' => ['value' => '2', 'parameters' => ['baz' => 'foo bar']],
            'odata.maxpagesize' => ['value' => '100', 'parameters' => []],
        ], HttpUtil::getPreferences($headers));
    }

    public function testMergePreferences(): void
    {
        $this->assertSame('', HttpUtil::mergePreferences([]));

        $this->assertSame(
            'respond-async, WAIT=5; foo=bar, handling=lenient, task_priority=2; baz="foo bar", odata.maxpagesize=100',
            HttpUtil::mergePreferences([
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
        $a = new Headers();
        $b = $a->set(self::HEADER_CONTENT_TYPE, self::TYPE_TEXT);
        $c = $b->set(self::HEADER_CONTENT_TYPE, self::TYPE_JSON);
        $d = $c->set(self::HEADER_CONTENT_TYPE, self::TYPE_JSON);
        $this->assertNotSame($b, $a);
        $this->assertNotSame($c, $b);
        $this->assertSame($d, $c);

        $a = new Headers();
        $b = $a->addValue(self::HEADER_PREFER, ['respond-async', 'wait=5', 'handling=lenient', 'task_priority=2']);
        $c = $b->addValue(self::HEADER_PREFER, 'odata.maxpagesize=100');
        $d = $c->set(self::HEADER_PREFER, [
            'respond-async',
            'wait=5',
            'handling=lenient',
            'task_priority=2',
            'odata.maxpagesize=100',
        ]);
        $e = $d->set(self::HEADER_PREFER, [
            'respond-async',
            'wait=5',
            'handling=lenient',
            'task_priority=2',
            'odata.maxpagesize=50',
        ]);
        $f = $e->merge(clone $d, false);
        $g = $e->merge(clone $e, false);
        $h = $g->merge([self::HEADER_PREFER => [
            'respond-async',
            'wait=5',
            'handling=lenient',
            'task_priority=2',
            'odata.maxpagesize=50',
        ]], false);
        $i = $h->merge([self::HEADER_PREFER => [
            'respond-async',
            'wait=5',
            'handling=lenient',
            'task_priority=2',
            'odata.maxpagesize=100',
        ]], false);
        $j = $i->unset(self::HEADER_PREFER);
        $k = $j->unset(self::HEADER_PREFER);
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
        $headers = (new Headers(['foo' => 'bar', 'baz' => '']))->addValue('foo', '')->set('qux', '');
        $this->assertSame(['foo' => ['bar', ''], 'baz' => [''], 'qux' => ['']], $headers->getHeaders());
        $this->assertSame(['foo: bar', 'baz: ', 'foo: ', 'qux: '], $headers->getLines());
        $this->assertSame(['foo:bar', 'baz;', 'foo;', 'qux;'], $headers->getLines('%s:%s', '%s;'));
    }

    public function testNormalise(): void
    {
        $headers1 = $this->getHeaders()->set('host', 'example.com');
        $headers2 = $headers1->normalise();
        $this->assertNotSame($headers1, $headers2);
        $this->assertSame($headers2, $headers2->normalise());
        $this->assertSame($all = [
            'host' => ['example.com'],
            'foo2' => ['*'],
            'foo' => ['bar', 'baz'],
            'abc' => ['def'],
            'qux' => ['quux'],
        ], $headers1->all());
        $this->assertSame($all, $headers2->all());
        $this->assertSame($headers = [
            'host' => ['example.com'],
            'Foo2' => ['*'],
            'foo' => ['bar', 'baz'],
            'abc' => ['def'],
            'qux' => ['quux'],
        ], $headers1->getHeaders());
        $this->assertSame($headers, $headers2->getHeaders());
        $this->assertNotSame($lines = [
            'host: example.com',
            'Foo2: *',
            'foo: bar',
            'abc: def',
            'Foo: baz',
            'qux: quux',
        ], $headers1->getLines());
        $this->assertSame($lines, $headers2->getLines());
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

    public function testMap(): void
    {
        $headers = $this->getHeaders();
        $this->assertSame([], $headers->map(fn() => [])->all());
        $this->assertSame([
            'foo2' => ['*-2'],
            'foo' => ['bar-2', 'baz-2'],
            'abc' => ['def-2'],
            'qux' => ['quux-2'],
        ], $headers->map(
            fn($values) =>
                array_map(fn($value) => $value . '-2', $values)
        )->all());
    }

    private function getHeaders(): Headers
    {
        return new Headers([
            'Foo2' => '*',
            'foo' => 'bar',
            'abc' => 'def',
            'Foo' => 'baz',
            'qux' => 'quux',
        ]);
    }

    public function testFilter(): void
    {
        $index = Arr::toIndex(Arr::lower(self::HEADERS_SENSITIVE));
        $token = new AccessToken('foo.bar.baz', 'Bearer', time() + 3600);
        $headers = (new Headers())
            ->authorize($token)
            ->set(self::HEADER_ACCEPT, '*/*')
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
        $this->assertTrue((new Headers(['foo' => 'bar']))->hasValue(['bar']));
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
        $headers = new Headers($items);
        $headers1 = $headers->only($keys);
        $headers2 = $headers1->only($keys);
        $this->assertNotSame($headers, $headers1);
        $this->assertInstanceOf(Headers::class, $headers1);
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
        $headers = new Headers($items);
        $headers1 = $headers->except($keys);
        $headers2 = $headers1->except($keys);
        $this->assertNotSame($headers, $headers1);
        $this->assertInstanceOf(Headers::class, $headers1);
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
        $headers = new Headers();
        $this->expectException(LogicException::class);
        $headers[self::HEADER_CONTENT_TYPE] = [self::TYPE_JSON];
    }

    public function testOffsetUnset(): void
    {
        $headers = new Headers();
        $this->expectException(LogicException::class);
        unset($headers[self::HEADER_CONTENT_TYPE]);
    }
}
