<?php declare(strict_types=1);

namespace Salient\Tests\Utility;

use Salient\Contract\Core\Jsonable;
use Salient\Tests\TestCase;
use Salient\Utility\Arr;
use Salient\Utility\Json;
use ArrayIterator;
use DateTimeImmutable;
use DateTimeInterface;
use OutOfRangeException;
use stdClass;
use Stringable;

/**
 * @covers \Salient\Utility\Arr
 */
final class ArrTest extends TestCase
{
    private const SORT_DATA = [
        'fubar',
        'foo' => 'foobar',
        'bar' => 'baz',
        'qux' => 'quux',
        83,
        71,
    ];

    public function testPluck(): void
    {
        $data = [
            'FOO' => ['foo' => 'bar', 'baz' => 'qux'],
            'BAR' => ['foo' => 'quux', 'baz' => 'quuux'],
        ];
        $this->assertSame(['bar', 'quux'], Arr::pluck($data, 'foo'));
        $this->assertSame(['bar', 'quux'], Arr::pluck($data, 'foo', false));
        $this->assertSame(['FOO' => 'bar', 'BAR' => 'quux'], Arr::pluck($data, 'foo', true));
        $this->assertSame(['bar' => 'qux', 'quux' => 'quuux'], Arr::pluck($data, 'baz', 'foo'));

        $data = [
            ['foo' => ['bar' => ['baz' => 'value1', 'quux' => 'key1']]],
            ['foo' => ['bar' => ['baz' => ['value2'], 'quux' => 'key2']]],
            ['foo' => ['bar' => ['quux' => 'key3']]],
        ];
        $this->assertSame(['value1', ['value2'], null], Arr::pluck($data, 'foo.bar.baz'));
        $this->assertSame(['key1' => 'value1', 'key2' => ['value2'], 'key3' => null], Arr::pluck($data, 'foo.bar.baz', 'foo.bar.quux'));

        $data[] = ['foo' => ['bar' => ['baz' => 4]]];
        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Value not found: foo.bar.quux');
        Arr::pluck($data, 'foo.bar.baz', 'foo.bar.quux');
    }

    /**
     * @dataProvider getProvider
     *
     * @param mixed $expected
     * @param array<array-key,mixed> $array
     * @param mixed $default
     */
    public function testGet($expected, array $array, string $key, $default = null): void
    {
        $this->assertSame($expected, Arr::get($array, $key, $default));
    }

    /**
     * @return array<array{mixed,array<array-key,mixed>,string,3?:mixed}>
     */
    public static function getProvider(): array
    {
        return [
            [
                'bar',
                ['foo' => 'bar'],
                'foo',
            ],
            [
                'qux',
                ['foo' => ['bar' => 'qux']],
                'foo.bar',
            ],
            [
                null,
                ['foo' => ['bar' => 'qux']],
                'baz',
                null,
            ],
            [
                null,
                ['foo' => ['bar' => 'qux']],
                'foo.baz',
                null,
            ],
            [
                null,
                ['foo' => ['bar' => 'qux']],
                'foo.bar.baz',
                null,
            ],
            [
                null,
                ['foo' => ['bar' => 'qux']],
                'foo.bar.baz.qux',
                null,
            ],
            [
                'default',
                ['foo' => ['bar' => 'qux']],
                'baz',
                'default',
            ],
            [
                null,
                ['foo' => ['bar' => null]],
                'foo.bar',
                'default',
            ],
        ];
    }

    /**
     * @dataProvider hasProvider
     *
     * @param array<array-key,mixed> $array
     */
    public function testHas(bool $expected, array $array, string $key): void
    {
        $this->assertSame($expected, Arr::has($array, $key));
    }

    /**
     * @return array<array{bool,array<array-key,mixed>,string}>
     */
    public static function hasProvider(): array
    {
        return [
            [
                true,
                ['foo' => 'bar'],
                'foo',
            ],
            [
                true,
                ['foo' => ['bar' => 'baz']],
                'foo.bar',
            ],
            [
                false,
                ['foo' => ['bar' => 'baz']],
                'baz',
            ],
            [
                false,
                ['foo' => ['bar' => 'baz']],
                'baz.qux',
            ],
            [
                true,
                ['foo' => ['bar' => ['baz' => 'qux']]],
                'foo.bar.baz',
            ],
            [
                false,
                ['foo' => ['bar' => ['baz' => 'qux']]],
                'foo.bar.baz.qux',
            ],
            [
                false,
                ['foo' => ['bar' => null]],
                'foo.bar.baz',
            ],
            [
                false,
                ['foo' => ['bar' => null]],
                'foo.bar.baz.qux',
            ],
            [
                true,
                ['foo' => ['bar' => ['baz' => null]]],
                'foo.bar.baz',
            ],
        ];
    }

    public function testGetThrowsException(): void
    {
        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Value not found: foo.bar');
        Arr::get([], 'foo.bar');
    }

    /**
     * @dataProvider extendProvider
     *
     * @param mixed[] $expected
     * @param mixed[] $array
     * @param mixed ...$values
     */
    public function testExtend(array $expected, array $array, ...$values): void
    {
        $this->assertSame($expected, Arr::extend($array, ...$values));
    }

    /**
     * @return array<array{mixed[],mixed[],...}>
     */
    public static function extendProvider(): array
    {
        return [
            [
                ['a', 'd', 'b', 'c'],
                ['a', 'd'],
                'a',
                'b',
                'c',
            ],
            [
                ['a', 'd', 'A', 'B', 'C'],
                ['a', 'd'],
                'A',
                'B',
                'C',
            ],
            [
                ['a', 'a', 'd', 'd', 'b', 'b', 'c', 'c'],
                ['a', 'a', 'd', 'd'],
                'a',
                'a',
                'a',
                'b',
                'b',
                'c',
                'c',
            ],
            [
                ['foo' => 'a', 'bar' => 'd', 'b', 'c'],
                ['foo' => 'a', 'bar' => 'd'],
                'a',
                'b',
                'c',
            ],
        ];
    }

    /**
     * @dataProvider firstProvider
     *
     * @param mixed $expected
     * @param mixed[] $array
     */
    public function testFirst($expected, array $array): void
    {
        $this->assertSame($expected, Arr::first($array));
    }

    /**
     * @return array<array{mixed,mixed[]}>
     */
    public static function firstProvider(): array
    {
        $object1 = new stdClass();
        $object2 = new stdClass();
        return [
            [null, []],
            [null, [null]],
            [true, [true, false]],
            [false, [false, true]],
            [0, [0, 1, 2]],
            [2, [2, 1, 0]],
            [0, [
                2 => 0,
                1 => 1,
                0 => 2,
            ]],
            [$object1, [$object1, $object2]],
            [$object2, [$object2, $object1]],
        ];
    }

    /**
     * @dataProvider flattenProvider
     *
     * @param mixed[] $expected
     * @param iterable<mixed> $array
     */
    public function testFlatten(array $expected, iterable $array, int $limit = -1, bool $preserveKeys = false): void
    {
        $this->assertSame($expected, Arr::flatten($array, $limit, $preserveKeys));
    }

    /**
     * @return array<array{mixed[],mixed[],2?:int,3?:bool}>
     */
    public static function flattenProvider(): array
    {
        $data = [
            'foo' => 0,
            'bar' => 1,
            'baz' => 2,
            [
                'foo' => null,
                'bar' => null,
                'baz' => [
                    'FOO' => 3,
                    'BAR' => 71,
                ],
            ],
        ];

        return [
            [
                [],
                [],
            ],
            [
                [1],
                [1],
            ],
            [
                [2],
                [1 => 2],
            ],
            [
                [1],
                [[1]],
            ],
            [
                [1],
                [[[1]]],
            ],
            [
                [[[1]]],
                [[[1]]],
                0,
            ],
            [
                [[1]],
                [[[1]]],
                1,
            ],
            [
                [1],
                [[[1]]],
                2,
            ],
            [
                [1],
                [[[1]]],
                3,
            ],
            [
                [[1]],
                [[[[1]]]],
                2,
            ],
            [
                [1, 'foo', null, true],
                [1, 'foo', [null, true]],
            ],
            [
                [1, 'foo', null, true],
                [[1, 'foo', [null, true]]],
            ],
            [
                [1, 'foo', null, true],
                [[[1, 'foo', [null, true]]]],
            ],
            [
                [[[1, 'foo', [null, true]]]],
                [[[1, 'foo', [null, true]]]],
                0,
            ],
            [
                [[1, 'foo', [null, true]]],
                [[[1, 'foo', [null, true]]]],
                1,
            ],
            [
                [1, 'foo', [null, true]],
                [[[1, 'foo', [null, true]]]],
                2,
            ],
            [
                [1, 'foo', null, true],
                [[[1, 'foo', [null, true]]]],
                3,
            ],
            [
                [[1, 'foo', [null, true]]],
                [[[[1, 'foo', [null, true]]]]],
                2,
            ],
            [
                [],
                [[[[[]]]]],
            ],
            [
                [[[[]]]],
                [[[[[]]]]],
                1,
            ],
            [
                [[]],
                [[[[[]]]]],
                3,
            ],
            [
                [],
                [],
                -1,
                true,
            ],
            [
                [1],
                [1],
                -1,
                true,
            ],
            [
                [1 => 2],
                [1 => 2],
                -1,
                true,
            ],
            [
                [1 => 2],
                [1 => 2],
                0,
                true,
            ],
            [
                [0, 1, 2, null, null, 3, 71],
                $data,
            ],
            [
                [0, 1, 2, null, null, ['FOO' => 3, 'BAR' => 71]],
                $data,
                1,
            ],
            [
                ['foo' => null, 'bar' => null, 'baz' => 2, 'FOO' => 3, 'BAR' => 71],
                $data,
                -1,
                true,
            ],
            [
                $data,
                $data,
                0,
                true,
            ],
            [
                ['foo' => null, 'bar' => null, 'baz' => ['FOO' => 3, 'BAR' => 71]],
                $data,
                1,
                true,
            ],
            [
                ['foo' => null, 'bar' => null, 'baz' => 2, 'FOO' => 3, 'BAR' => 71],
                $data,
                2,
                true,
            ],
        ];
    }

    /**
     * @dataProvider implodeProvider
     *
     * @param iterable<int|float|string|bool|Stringable|null> $array
     */
    public function testImplode(string $expected, string $separator, iterable $array, ?string $characters = null): void
    {
        $this->assertSame($expected, Arr::implode($separator, $array, $characters));
    }

    /**
     * @return array<array{string,string,iterable<int|float|string|bool|Stringable|null>,3?:string|null}>
     */
    public static function implodeProvider(): iterable
    {
        return [
            [
                '0,a, ,-1,0,1,3.14,1',
                ',',
                ['0', '', 'a', ' ', -1, 0, 1, false, 3.14, null, true],
                '',
            ],
            [
                '0 a   -1 0 1 3.14 1',
                ' ',
                ['0', '', 'a', ' ', -1, 0, 1, false, 3.14, null, true],
                '',
            ],
            [
                '',
                ' ',
                [],
            ],
            [
                '',
                ' ',
                ['', false, null],
            ],
            [
                '',
                ',',
                [],
            ],
            [
                '',
                ',',
                [''],
            ],
            [
                '',
                ',',
                [' '],
            ],
            [
                '',
                ',',
                [' ', "\t"],
            ],
            [
                " ,\t",
                ',',
                [' ', "\t"],
                '',
            ],
            [
                '0,1,1,a,b,c',
                ',',
                [null, 0, 1, true, false, ' ', 'a ', ' b ', ' c'],
            ],
            [
                '0,1,1, ,a,b,c',
                ',',
                [null, 0, 1, true, false, ' ', '/', 'a/', '/b/', '/c'],
                '/',
            ],
        ];
    }

    /**
     * @dataProvider hasNumericKeysProvider
     *
     * @param mixed $value
     */
    public function testHasNumericKeys(bool $expected, $value, bool $allowEmpty = false): void
    {
        $this->assertSame($expected, Arr::hasNumericKeys($value, $allowEmpty));
    }

    /**
     * @return array<array{bool,mixed,2?:bool}>
     */
    public static function hasNumericKeysProvider(): array
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
     * @dataProvider isListProvider
     *
     * @param mixed $value
     */
    public function testIsList(bool $expected, $value, bool $allowEmpty = false): void
    {
        $this->assertSame($expected, Arr::isList($value, $allowEmpty));
    }

    /**
     * @return array<array{bool,mixed,2?:bool}>
     */
    public static function isListProvider(): array
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
     * @dataProvider keyOfProvider
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param TKey|null $expected
     * @param array<TKey,TValue> $array
     * @param TValue $value
     */
    public function testKeyOf($expected, array $array, $value): void
    {
        if ($expected === null) {
            $this->expectException(OutOfRangeException::class);
            $this->expectExceptionMessage('Value not found in array');
        }
        $this->assertSame($expected, Arr::keyOf($array, $value));
    }

    /**
     * @dataProvider keyOfProvider
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param TKey|null $expected
     * @param array<TKey,TValue> $array
     * @param TValue $value
     */
    public function testSearch($expected, array $array, $value): void
    {
        $this->assertSame($expected, Arr::search($array, $value));
    }

    /**
     * @return array<array{array-key|null,mixed[],mixed}>
     */
    public static function keyOfProvider(): array
    {
        $a = new stdClass();
        $b = new stdClass();
        $objects = [
            'foo' => $a,
            'bar' => $b,
            'qux' => $b,
        ];
        $falsey = [
            'foo' => null,
            'bar' => false,
            'qux' => 0,
            '',
        ];

        return [
            [
                'foo',
                $falsey,
                null,
            ],
            [
                'bar',
                $falsey,
                false,
            ],
            [
                'qux',
                $falsey,
                0,
            ],
            [
                0,
                $falsey,
                '',
            ],
            [
                'foo',
                $objects,
                $a,
            ],
            [
                'bar',
                $objects,
                $b,
            ],
            [
                0,
                [$a],
                $a,
            ],
            [
                null,
                [],
                $a,
            ],
        ];
    }

    /**
     * @dataProvider offsetOfKeyProvider
     *
     * @param int|string $expected
     * @param mixed[] $array
     * @param array-key $key
     */
    public function testOffsetOfKey($expected, array $array, $key): void
    {
        $this->maybeExpectException($expected);
        $this->assertSame($expected, Arr::offsetOfKey($array, $key));
    }

    /**
     * @return array<array{int|string,mixed[],array-key}>
     */
    public static function offsetOfKeyProvider(): array
    {
        $data = [
            'foo' => 'bar',
            'baz' => 'qux',
            71 => 'quux',
            83 => 'quuux',
        ];

        return [
            [0, $data, 'foo'],
            [1, $data, 'baz'],
            [2, $data, 71],
            [3, $data, 83],
            [OutOfRangeException::class . ',Array key not found: 0', $data, 0],
            [OutOfRangeException::class . ',Array key not found: 1', $data, 1],
            [OutOfRangeException::class . ',Array key not found: bar', $data, 'bar'],
        ];
    }

    /**
     * @dataProvider lastProvider
     *
     * @param mixed $expected
     * @param mixed[] $array
     */
    public function testLast($expected, array $array): void
    {
        $this->assertSame($expected, Arr::last($array));
    }

    /**
     * @return array<array{mixed,mixed[]}>
     */
    public static function lastProvider(): array
    {
        $object1 = new stdClass();
        $object2 = new stdClass();
        return [
            [null, []],
            [null, [null]],
            [false, [true, false]],
            [true, [false, true]],
            [2, [0, 1, 2]],
            [0, [2, 1, 0]],
            [2, [
                2 => 0,
                1 => 1,
                0 => 2,
            ]],
            [$object2, [$object1, $object2]],
            [$object1, [$object2, $object1]],
        ];
    }

    /**
     * @dataProvider wrapListProvider
     *
     * @param mixed[] $expected
     * @param mixed $value
     */
    public function testWrapList(array $expected, $value): void
    {
        $this->assertSame($expected, Arr::wrapList($value));
    }

    /**
     * @return array<array{mixed[],mixed}>
     */
    public static function wrapListProvider(): array
    {
        return [
            [[], null],
            [[0], 0],
            [[0], [0]],
            [[1], 1],
            [[1], [1]],
            [[false], false],
            [[false], [false]],
            [[true], true],
            [[true], [true]],
            [[''], ''],
            [[''], ['']],
            [['a'], 'a'],
            [['a'], ['a']],
            [['a', 'b'], ['a', 'b']],
            [[[7 => 'a', 1 => 'b']], [7 => 'a', 1 => 'b']],
            [[['a' => 'a', 'b' => 'b']], ['a' => 'a', 'b' => 'b']],
        ];
    }

    /**
     * @dataProvider lowerProvider
     *
     * @param string[] $expected
     * @param array<int|float|string|bool|Stringable|null> $array
     */
    public function testLower($expected, $array): void
    {
        $this->assertSame($expected, Arr::lower($array));
    }

    /**
     * @return array<array{string[],array<int|float|string|bool|Stringable|null>}>
     */
    public static function lowerProvider(): array
    {
        return [
            [
                [],
                [],
            ],
            [
                [
                    '0',
                    '3.14',
                    'null' => '',
                    '1',
                    'false' => '',
                    'string' => 'title',
                    Stringable::class => "i'm batman.",
                ],
                [
                    0,
                    3.14,
                    'null' => null,
                    true,
                    'false' => false,
                    'string' => 'TITLE',
                    Stringable::class => new class implements Stringable {
                        public function __toString()
                        {
                            return "I'm Batman.";
                        }
                    },
                ],
            ],
        ];
    }

    /**
     * @dataProvider ofProvider
     *
     * @template T of object
     *
     * @param mixed $value
     * @param class-string<T> $class
     */
    public function testOf(
        $value,
        string $class,
        bool $expected,
        bool $expectedIfOrEmpty
    ): void {
        $this->assertSame($expected, Arr::of($value, $class));
        $this->assertSame($expectedIfOrEmpty, Arr::of($value, $class, true));
    }

    /**
     * @return array<string,array{mixed,string,bool,bool}>
     */
    public static function ofProvider(): array
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
            'datetimes (indexed)' => [[7 => $now(), 1 => $now()], DateTimeInterface::class, true, true],
            'datetimes (associative)' => [['from' => $now(), 'to' => $now()], DateTimeInterface::class, true, true],
            'mixed #1' => [[0, 'a', $now()], DateTimeInterface::class, false, false],
            'mixed #2' => [[0, 1, true], DateTimeInterface::class, false, false],
            'mixed #3' => [[0, 1, null], DateTimeInterface::class, false, false],
            'mixed #4' => [['a', 'b', true], DateTimeInterface::class, false, false],
            'mixed #5' => [['a', 'b', null], DateTimeInterface::class, false, false],
            'mixed #6' => [[$now, $now, true], DateTimeInterface::class, false, false],
            'mixed #7' => [[$now, $now, null], DateTimeInterface::class, false, false],
        ];
    }

    /**
     * @dataProvider ofArrayKeyProvider
     *
     * @param mixed $value
     */
    public function testOfArrayKey(
        $value,
        bool $expected,
        bool $expectedIfOrEmpty
    ): void {
        $this->assertSame($expected, Arr::ofArrayKey($value));
        $this->assertSame($expectedIfOrEmpty, Arr::ofArrayKey($value, true));
    }

    /**
     * @return array<string,array{mixed,bool,bool}>
     */
    public static function ofArrayKeyProvider(): array
    {
        return [
            'null' => [null, false, false],
            'int' => [0, false, false],
            'string' => ['a', false, false],
            'empty' => [[], false, true],
            'ints' => [[0, 1], true, true],
            'ints (indexed)' => [[7 => 0, 1 => 1], true, true],
            'ints (associative)' => [['a' => 0, 'b' => 1], true, true],
            'strings' => [['a', 'b'], true, true],
            'strings (indexed)' => [[7 => 'a', 1 => 'b'], true, true],
            'strings (associative)' => [['a' => 'a', 'b' => 'b'], true, true],
            'mixed #1' => [[0, 'a'], false, false],
            'mixed #2' => [[0, 1, true], false, false],
            'mixed #3' => [[0, 1, null], false, false],
            'mixed #4' => [['a', 'b', true], false, false],
            'mixed #5' => [['a', 'b', null], false, false],
        ];
    }

    /**
     * @dataProvider ofIntProvider
     *
     * @param mixed $value
     */
    public function testOfInt(
        $value,
        bool $expected,
        bool $expectedIfOrEmpty
    ): void {
        $this->assertSame($expected, Arr::ofInt($value));
        $this->assertSame($expectedIfOrEmpty, Arr::ofInt($value, true));
    }

    /**
     * @return array<string,array{mixed,bool,bool}>
     */
    public static function ofIntProvider(): array
    {
        return [
            'null' => [null, false, false],
            'int' => [0, false, false],
            'string' => ['a', false, false],
            'empty' => [[], false, true],
            'ints' => [[0, 1], true, true],
            'ints (indexed)' => [[7 => 0, 1 => 1], true, true],
            'ints (associative)' => [['a' => 0, 'b' => 1], true, true],
            'strings' => [['a', 'b'], false, false],
            'mixed #1' => [[0, 'a'], false, false],
            'mixed #2' => [[0, 1, true], false, false],
            'mixed #3' => [[0, 1, null], false, false],
            'mixed #4' => [['a', 'b', true], false, false],
            'mixed #5' => [['a', 'b', null], false, false],
        ];
    }

    /**
     * @dataProvider ofStringProvider
     *
     * @param mixed $value
     */
    public function testOfString(
        $value,
        bool $expected,
        bool $expectedIfOrEmpty
    ): void {
        $this->assertSame($expected, Arr::ofString($value));
        $this->assertSame($expectedIfOrEmpty, Arr::ofString($value, true));
    }

    /**
     * @return array<string,array{mixed,bool,bool}>
     */
    public static function ofStringProvider(): array
    {
        return [
            'null' => [null, false, false],
            'int' => [0, false, false],
            'string' => ['a', false, false],
            'empty' => [[], false, true],
            'ints' => [[0, 1], false, false],
            'strings' => [['a', 'b'], true, true],
            'strings (indexed)' => [[7 => 'a', 1 => 'b'], true, true],
            'strings (associative)' => [['a' => 'a', 'b' => 'b'], true, true],
            'mixed #1' => [[0, 'a'], false, false],
            'mixed #2' => [[0, 1, true], false, false],
            'mixed #3' => [[0, 1, null], false, false],
            'mixed #4' => [['a', 'b', true], false, false],
            'mixed #5' => [['a', 'b', null], false, false],
        ];
    }

    /**
     * @dataProvider popProvider
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $expected
     * @param TValue|null $expectedPopped
     * @param array<TKey,TValue> $array
     */
    public function testPop(array $expected, $expectedPopped, array $array): void
    {
        $popped = null;
        $this->assertSame($expected, Arr::pop($array, $popped));
        $this->assertSame($expectedPopped, $popped);
    }

    /**
     * @return array<array{mixed[],mixed,mixed[]}>
     */
    public static function popProvider(): array
    {
        return [
            [['a', 'b'], 'c', ['a', 'b', 'c']],
            [[0, 1], 2, [0, 1, 2]],
            [[false], true, [false, true]],
            [[true], false, [true, false]],
            [[], null, [null]],
            [[], null, []],
            [['a' => 'foo'], 'bar', ['a' => 'foo', 'b' => 'bar']],
        ];
    }

    /**
     * @dataProvider pushProvider
     *
     * @param mixed[] $expected
     * @param mixed[] $array
     * @param mixed ...$values
     */
    public function testPush(array $expected, array $array, ...$values): void
    {
        $this->assertSame($expected, Arr::push($array, ...$values));
    }

    /**
     * @return array<array{mixed[],mixed[],...}>
     */
    public static function pushProvider(): array
    {
        return [
            [['a', 'b', 'c', 'd', 'e'], ['a', 'b', 'c'], 'd', 'e'],
            [[0, 1, 2, 3, 4], [0, 1, 2], 3, 4],
            [[false, true, true, false], [false, true], true, false],
            [[true, false, false, true], [true, false], false, true],
            [[], []],
            [[null], [], null],
            [[false], [], false],
            [[0], [], 0],
            [['a' => 'foo', 'b' => 'bar', 'baz', 'qux'], ['a' => 'foo', 'b' => 'bar'], 'baz', 'qux'],
        ];
    }

    /**
     * @dataProvider renameProvider
     *
     * @param mixed[]|string $expected
     * @param mixed[] $array
     * @param array-key $key
     * @param array-key $newKey
     */
    public function testRename($expected, array $array, $key, $newKey): void
    {
        $this->maybeExpectException($expected);
        $this->assertSame($expected, Arr::rename($array, $key, $newKey));
    }

    /**
     * @return array<array{mixed[]|string,mixed[],array-key,array-key}>
     */
    public static function renameProvider(): array
    {
        $array = [
            'a' => 'value0',
            'b' => 'value1',
            'A' => 'value2',
            'B' => null,
        ];

        return [
            [
                $array,
                $array,
                'b',
                'b',
            ],
            [
                OutOfRangeException::class . ',Array key not found: foo',
                $array,
                'foo',
                'bar',
            ],
            [
                [
                    'a' => 'value0',
                    'b_2' => 'value1',
                    'A' => 'value2',
                    'B' => null,
                ],
                $array,
                'b',
                'b_2',
            ],
            [
                [
                    'a' => 'value0',
                    'b' => 'value1',
                    'A' => 'value2',
                    0 => null,
                ],
                $array,
                'B',
                0,
            ],
        ];
    }

    /**
     * @dataProvider sameProvider1
     * @dataProvider sameValuesProvider
     *
     * @param mixed[] ...$arrays
     */
    public function testSameValues(bool $expected, array ...$arrays): void
    {
        $this->assertSame($expected, Arr::sameValues(...$arrays));
    }

    /**
     * @dataProvider sameProvider1
     * @dataProvider sameProvider2
     *
     * @param mixed[] ...$arrays
     */
    public function testSame(bool $expected, array ...$arrays): void
    {
        $this->assertSame($expected, Arr::same(...$arrays));
    }

    /**
     * @return array<array{bool,...}>
     */
    public static function sameProvider1(): array
    {
        return [
            [
                true,
                [],
                [],
            ],
            [
                true,
                [],
                [],
                [],
            ],
            [
                true,
                [null],
                [null],
            ],
            [
                false,
                [null],
                [],
            ],
            [
                true,
                ['a'],
                ['a'],
            ],
            [
                false,
                ['a'],
                ['a', 'a'],
            ],
            [
                true,
                [0],
                [0],
            ],
            [
                false,
                [0],
                [0, 0],
            ],
            [
                true,
                [1],
                [1],
            ],
            [
                false,
                [1],
                [1, 1],
            ],
            [
                false,
                [1],
                ['1'],
            ],
            [
                true,
                ['a', 1, null, true],
                ['a', 1, null, true],
            ],
        ];
    }

    /**
     * @return array<array{bool,mixed[],...}>
     */
    public static function sameValuesProvider(): array
    {
        return [
            [
                true,
                ['a', 1, null, true],
                [true, null, 1, 'a'],
            ],
            [
                true,
                ['a', 1, null, true],
                ['foo' => true, 'bar' => null, 'qux' => 1, 'quux' => 'a'],
            ],
            [
                false,
                ['a', 1, null, true],
                [true, false, 1, 'a'],
            ],
        ];
    }

    /**
     * @return array<array{bool,mixed[],...}>
     */
    public static function sameProvider2(): array
    {
        return [
            [
                false,
                ['a', 1, null, true],
                [true, null, 1, 'a'],
            ],
            [
                false,
                ['a', 1, null, true],
                ['foo' => true, 'bar' => null, 'qux' => 1, 'quux' => 'a'],
            ],
            [
                true,
                ['a', 1, null, true],
                [3 => true, 2 => null, 1 => 1, 0 => 'a'],
                [0 => 'a', 3 => true, 2 => null, 1 => 1],
            ],
            [
                false,
                ['a', 1, null, true],
                [3 => true, 2 => null, 1 => 1, 0 => 'a'],
                [true, null, 1, 'a'],
            ],
            [
                true,
                ['quux' => 'a', 'qux' => 1, 'bar' => null, 'foo' => true],
                ['foo' => true, 'bar' => null, 'qux' => 1, 'quux' => 'a'],
            ],
            [
                true,
                [0 => false, 'quux' => 'a', 'qux' => 1, 'bar' => null, 'foo' => true],
                ['foo' => true, 'bar' => null, 'qux' => 1, 'quux' => 'a', 0 => false],
            ],
        ];
    }

    /**
     * @dataProvider shiftProvider
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $expected
     * @param TValue|null $expectedShifted
     * @param array<TKey,TValue> $array
     */
    public function testShift(array $expected, $expectedShifted, array $array): void
    {
        $shifted = null;
        $this->assertSame($expected, Arr::shift($array, $shifted));
        $this->assertSame($expectedShifted, $shifted);
    }

    /**
     * @return array<array{mixed[],mixed,mixed[]}>
     */
    public static function shiftProvider(): array
    {
        return [
            [['b', 'c'], 'a', ['a', 'b', 'c']],
            [[1, 2], 0, [0, 1, 2]],
            [[true], false, [false, true]],
            [[false], true, [true, false]],
            [[], null, [null]],
            [[], null, []],
            [['b' => 'bar'], 'foo', ['a' => 'foo', 'b' => 'bar']],
        ];
    }

    /**
     * @dataProvider sortProvider
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param mixed[] $expected
     * @param array<TKey,TValue> $array
     * @param (callable(TValue, TValue): int)|int-mask-of<Arr::SORT_*> $callbackOrFlags
     */
    public function testSort(array $expected, array $array, bool $preserveKeys = false, $callbackOrFlags = \SORT_REGULAR): void
    {
        $this->assertSame($expected, Arr::sort($array, $preserveKeys, $callbackOrFlags));
        if (!is_callable($callbackOrFlags)) {
            $this->assertSame(array_reverse($expected, $preserveKeys), Arr::sortDesc($array, $preserveKeys, $callbackOrFlags));
        }
    }

    /**
     * @return array<array{mixed[],mixed[],2?:bool,3?:callable|int}>
     */
    public static function sortProvider(): array
    {
        return [
            [
                [
                    71,
                    83,
                    'baz',
                    'foobar',
                    'fubar',
                    'quux',
                ],
                self::SORT_DATA,
                false,
                \SORT_STRING,
            ],
            [
                [
                    2 => 71,
                    1 => 83,
                    'bar' => 'baz',
                    'foo' => 'foobar',
                    0 => 'fubar',
                    'qux' => 'quux',
                ],
                self::SORT_DATA,
                true,
                \SORT_STRING,
            ],
            [
                [
                    'foobar',
                    'fubar',
                    'quux',
                    'baz',
                    71,
                    83,
                ],
                self::SORT_DATA,
                false,
                fn($a, $b) =>
                    strlen((string) $b) <=> strlen((string) $a)
                        ?: (string) $a <=> (string) $b,
            ],
            [
                [
                    'foo' => 'foobar',
                    0 => 'fubar',
                    'qux' => 'quux',
                    'bar' => 'baz',
                    2 => 71,
                    1 => 83,
                ],
                self::SORT_DATA,
                true,
                fn($a, $b) =>
                    strlen((string) $b) <=> strlen((string) $a)
                        ?: (string) $a <=> (string) $b,
            ],
        ];
    }

    /**
     * @dataProvider sortByKeyProvider
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param mixed[] $expected
     * @param array<TKey,TValue> $array
     * @param (callable(TKey, TKey): int)|int-mask-of<Arr::SORT_*> $callbackOrFlags
     */
    public function testSortByKey(array $expected, array $array, $callbackOrFlags = \SORT_REGULAR): void
    {
        $this->assertSame($expected, Arr::sortByKey($array, $callbackOrFlags));
        if (!is_callable($callbackOrFlags)) {
            $this->assertSame(array_reverse($expected, true), Arr::sortByKeyDesc($array, $callbackOrFlags));
        }
    }

    /**
     * @return array<array{mixed[],mixed[],2?:callable|int}>
     */
    public static function sortByKeyProvider(): array
    {
        $data = array_flip(self::SORT_DATA);

        return [
            [
                [
                    71 => 2,
                    83 => 1,
                    'baz' => 'bar',
                    'foobar' => 'foo',
                    'fubar' => 0,
                    'quux' => 'qux',
                ],
                $data,
                \SORT_STRING,
            ],
            [
                [
                    'foobar' => 'foo',
                    'fubar' => 0,
                    'quux' => 'qux',
                    'baz' => 'bar',
                    71 => 2,
                    83 => 1,
                ],
                $data,
                fn($a, $b) =>
                    strlen((string) $b) <=> strlen((string) $a)
                        ?: (string) $a <=> (string) $b,
            ],
        ];
    }

    /**
     * @dataProvider spliceProvider
     *
     * @param mixed[]|string $expected
     * @param mixed[]|null $expectedReplaced
     * @param mixed[] $array
     * @param mixed[] $replacement
     */
    public function testSplice(
        $expected,
        $expectedReplaced,
        array $array,
        int $offset,
        ?int $length = null,
        $replacement = []
    ): void {
        $replaced = null;
        $this->maybeExpectException($expected);
        $this->assertSame($expected, Arr::splice($array, $offset, $length, $replacement, $replaced));
        $this->assertSame($expectedReplaced, $replaced);
    }

    /**
     * @return array<array{mixed[]|string,mixed[]|null,mixed[],int,4?:int|null,5?:mixed[]}>
     */
    public static function spliceProvider(): array
    {
        $array1 = [
            'a' => 'value0',
            'b' => 'value1',
            'A' => 'value2',
            'B' => 'value3',
        ];

        $array2 = [
            'c' => 'value4',
            'C' => 'value5',
        ];

        return [
            'remove all' => [
                [],
                [
                    'a' => 'value0',
                    'b' => 'value1',
                    'A' => 'value2',
                    'B' => 'value3',
                ],
                $array1,
                0,
            ],
            'remove some' => [
                [
                    'a' => 'value0',
                    'b' => 'value1',
                ],
                [
                    'A' => 'value2',
                    'B' => 'value3',
                ],
                $array1,
                2,
            ],
            'remove last' => [
                [
                    'a' => 'value0',
                    'b' => 'value1',
                    'A' => 'value2',
                ],
                [
                    'B' => 'value3',
                ],
                $array1,
                3,
            ],
            'remove none' => [
                [
                    'a' => 'value0',
                    'b' => 'value1',
                    'A' => 'value2',
                    'B' => 'value3',
                ],
                [],
                $array1,
                1,
                0,
            ],
            'out of range' => [
                [
                    'a' => 'value0',
                    'b' => 'value1',
                    'A' => 'value2',
                    'B' => 'value3',
                ],
                [],
                $array1,
                10,
            ],
            'insert at start' => [
                [
                    'value4',
                    'value5',
                    'a' => 'value0',
                    'b' => 'value1',
                    'A' => 'value2',
                    'B' => 'value3',
                ],
                [],
                $array1,
                0,
                0,
                $array2,
            ],
            'replace middle' => [
                [
                    'a' => 'value0',
                    'value4',
                    'value5',
                    'B' => 'value3',
                ],
                [
                    'b' => 'value1',
                    'A' => 'value2',
                ],
                $array1,
                1,
                2,
                $array2,
            ],
        ];
    }

    /**
     * @dataProvider spliceByKeyProvider
     *
     * @param mixed[]|string $expected
     * @param mixed[]|null $expectedReplaced
     * @param mixed[] $array
     * @param array-key $key
     * @param mixed[] $replacement
     */
    public function testSpliceByKey(
        $expected,
        $expectedReplaced,
        array $array,
        $key,
        ?int $length = null,
        array $replacement = []
    ): void {
        $replaced = null;
        $this->maybeExpectException($expected);
        $this->assertSame($expected, Arr::spliceByKey($array, $key, $length, $replacement, $replaced));
        $this->assertSame($expectedReplaced, $replaced);
    }

    /**
     * @return array<array{mixed[]|string,mixed[]|null,mixed[],array-key,4?:int|null,5?:mixed[]}>
     */
    public static function spliceByKeyProvider(): array
    {
        $array1 = [
            'a' => 'value0',
            'b' => 'value1',
            'A' => 'value2',
            'B' => 'value3',
        ];

        $array2 = [
            'c' => 'value4',
            'C' => 'value5',
        ];

        return [
            'remove all' => [
                [],
                [
                    'a' => 'value0',
                    'b' => 'value1',
                    'A' => 'value2',
                    'B' => 'value3',
                ],
                $array1,
                'a',
            ],
            'remove some' => [
                [
                    'a' => 'value0',
                    'b' => 'value1',
                ],
                [
                    'A' => 'value2',
                    'B' => 'value3',
                ],
                $array1,
                'A',
            ],
            'remove last' => [
                [
                    'a' => 'value0',
                    'b' => 'value1',
                    'A' => 'value2',
                ],
                [
                    'B' => 'value3',
                ],
                $array1,
                'B',
            ],
            'remove none' => [
                [
                    'a' => 'value0',
                    'b' => 'value1',
                    'A' => 'value2',
                    'B' => 'value3',
                ],
                [],
                $array1,
                'b',
                0,
            ],
            'out of range' => [
                OutOfRangeException::class . ',Array key not found: foo',
                null,
                $array1,
                'foo',
            ],
            'insert at start' => [
                [
                    'c' => 'value4',
                    'C' => 'value5',
                    'a' => 'value0',
                    'b' => 'value1',
                    'A' => 'value2',
                    'B' => 'value3',
                ],
                [],
                $array1,
                'a',
                0,
                $array2,
            ],
            'replace middle' => [
                [
                    'a' => 'value0',
                    'c' => 'value4',
                    'C' => 'value5',
                    'B' => 'value3',
                ],
                [
                    'b' => 'value1',
                    'A' => 'value2',
                ],
                $array1,
                'b',
                2,
                $array2,
            ],
        ];
    }

    /**
     * @dataProvider toIndexProvider
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $expected
     * @param array<array-key,TKey> $array
     * @param TValue $value
     */
    public function testToIndex(array $expected, array $array, $value = true): void
    {
        $this->assertSame($expected, Arr::toIndex($array, $value));
        $this->assertSame($expected, Arr::toIndex(new ArrayIterator($array), $value));
    }

    /**
     * @return array<array{mixed[],mixed[],2?:mixed}>
     */
    public static function toIndexProvider(): array
    {
        return [
            [
                [],
                [],
            ],
            [
                ['foo' => true, 'bar' => true],
                ['foo', 'bar'],
            ],
            [
                ['foo' => null, 'bar' => null],
                ['foo', 'bar'],
                null,
            ],
            [
                ['foo' => 'qux', 'bar' => 'qux'],
                ['foo', 'bar'],
                'qux',
            ],
        ];
    }

    /**
     * @dataProvider toMapProvider
     *
     * @template TValue of ArrayAccess|array|object
     *
     * @param array<TValue> $expected
     * @param array<TValue> $array
     * @param array-key $key
     */
    public function testToMap(array $expected, array $array, $key): void
    {
        $this->assertSame($expected, Arr::toMap($array, $key));
    }

    /**
     * @return array<array{mixed[],mixed[],array-key}>
     */
    public static function toMapProvider(): array
    {
        $arrays = [
            ['id' => 32, 'name' => 'Greta'],
            ['id' => 71, 'name' => 'Terry'],
            ['id' => 83, 'name' => 'Terry'],
        ];

        $a = new stdClass();
        $a->id = 32;
        $a->name = 'Greta';

        $b = new stdClass();
        $b->id = 71;
        $b->name = 'Terry';

        $c = new stdClass();
        $c->id = 83;
        $c->name = 'Terry';

        $objects = [$a, $b, $c];

        return [
            'arrays' => [
                [
                    32 => ['id' => 32, 'name' => 'Greta'],
                    71 => ['id' => 71, 'name' => 'Terry'],
                    83 => ['id' => 83, 'name' => 'Terry'],
                ],
                $arrays,
                'id',
            ],
            'arrays + duplicated key' => [
                [
                    'Greta' => ['id' => 32, 'name' => 'Greta'],
                    'Terry' => ['id' => 83, 'name' => 'Terry'],
                ],
                $arrays,
                'name',
            ],
            'objects' => [
                [
                    32 => $a,
                    71 => $b,
                    83 => $c,
                ],
                $objects,
                'id',
            ],
            'objects + duplicated key' => [
                [
                    'Greta' => $a,
                    'Terry' => $c,
                ],
                $objects,
                'name',
            ],
        ];
    }

    /**
     * @dataProvider toScalarsProvider
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool
     * @template TNull of TValue|null
     *
     * @param array<TKey,TValue|TNull|string> $expected
     * @param iterable<TKey,TValue|mixed[]|object|null> $array
     * @param TNull $null
     */
    public function testToScalars(array $expected, iterable $array, $null = null): void
    {
        $this->assertSame($expected, Arr::toScalars($array, $null));
    }

    /**
     * @return array<array{mixed[],mixed[],2?:mixed}>
     */
    public static function toScalarsProvider(): array
    {
        $a = new class {
            public function __toString()
            {
                return Stringable::class;
            }
        };

        $b = new class implements Jsonable {
            public function toJson(int $flags = 0): string
            {
                return Json::encode([Jsonable::class => true], $flags);
            }
        };

        return [
            [
                [],
                [],
            ],
            [
                [null, 0, 3.14, true, false, '', 'a', '[1,2,3]', '{"foo":"bar"}', Stringable::class, '{"Salient\\\\Contract\\\\Core\\\\Jsonable":true}'],
                [null, 0, 3.14, true, false, '', 'a', [1, 2, 3], ['foo' => 'bar'], $a, $b],
            ],
            [
                ['NULL', 0, false, '', '[null,"a",1]'],
                [null, 0, false, '', [null, 'a', 1]],
                'NULL',
            ],
        ];
    }

    /**
     * @dataProvider toStringsProvider
     *
     * @template TKey of array-key
     * @template TNull of string|null
     *
     * @param array<TKey,TNull|string> $expected
     * @param iterable<TKey,mixed[]|object|int|float|string|bool|null> $array
     * @param TNull $null
     */
    public function testToStrings(array $expected, iterable $array, ?string $null = null): void
    {
        $this->assertSame($expected, Arr::toStrings($array, $null));
    }

    /**
     * @return array<array{mixed[],mixed[],2?:string|null}>
     */
    public static function toStringsProvider(): array
    {
        $a = new class {
            public function __toString()
            {
                return Stringable::class;
            }
        };

        $b = new class implements Jsonable {
            public function toJson(int $flags = 0): string
            {
                return Json::encode([Jsonable::class => true], $flags);
            }
        };

        return [
            [
                [],
                [],
            ],
            [
                [null, '0', '3.14', '1', '', '', 'a', '[1,2,3]', '{"foo":"bar"}', Stringable::class, '{"Salient\\\\Contract\\\\Core\\\\Jsonable":true}'],
                [null, 0, 3.14, true, false, '', 'a', [1, 2, 3], ['foo' => 'bar'], $a, $b],
            ],
            [
                ['NULL', '0', '', '', '[null,"a",1]'],
                [null, 0, false, '', [null, 'a', 1]],
                'NULL',
            ],
        ];
    }

    public function testSet(): void
    {
        $array = ['foo' => 'bar', 'baz' => 'qux'];
        $this->assertSame(['foo' => ['bar', 'quux'], 'baz' => 'qux'], Arr::set($array, 'foo', ['bar', 'quux']));
        $this->assertSame(['foo' => 'bar', 'baz' => 'qux', 'bar' => 'baz'], Arr::set($array, 'bar', 'baz'));
    }

    public function testSetIf(): void
    {
        $array = ['foo' => 'bar', 'baz' => 'qux'];
        $this->assertSame(['foo' => 'bar', 'baz' => 'qux'], Arr::setIf($array, 'foo', ['bar', 'quux']));
        $this->assertSame(['foo' => 'bar', 'baz' => 'qux', 'bar' => 'baz'], Arr::setIf($array, 'bar', 'baz'));
    }

    /**
     * @dataProvider trimProvider
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool|Stringable|null
     *
     * @param array<TKey,string|null>|list<string> $expected
     * @param iterable<TKey,TValue> $array
     */
    public function testTrim(array $expected, iterable $array, ?string $characters = null, bool $removeEmpty = true, bool $nullEmpty = false): void
    {
        $this->assertSame($expected, Arr::trim($array, $characters, $removeEmpty, $nullEmpty));
    }

    /**
     * @return array<array{mixed[],mixed[],2?:string|null,3?:bool,4?:bool}>
     */
    public static function trimProvider(): array
    {
        return [
            [
                [],
                [],
            ],
            [
                [],
                [''],
            ],
            [
                [],
                [' '],
            ],
            [
                [],
                [' ', "\t"],
            ],
            [
                ['', ''],
                [' ', "\t"],
                null,
                false,
            ],
            [
                ['0', '1', '1', 'a', 'b', 'c'],
                [null, 0, 1, true, false, ' ', 'a' => 'a ', 'b' => ' b ', 'c' => ' c', 'd' => ' '],
            ],
            [
                ['0', '1', '1', ' ', 'a', 'b', 'c'],
                [null, 0, 1, true, false, ' ', '/', 'a' => 'a/', 'b' => '/b/', 'c' => '/c', 'd' => '/'],
                '/',
            ],
            [
                ['', '0', '1', '1', '', '', 'a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => ''],
                [null, 0, 1, true, false, ' ', 'a' => 'a ', 'b' => ' b ', 'c' => ' c', 'd' => ' '],
                null,
                false,
            ],
            [
                [null, '0', '1', '1', null, null, 'a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => null],
                [null, 0, 1, true, false, ' ', 'a' => 'a ', 'b' => ' b ', 'c' => ' c', 'd' => ' '],
                null,
                false,
                true,
            ],
        ];
    }

    /**
     * @dataProvider uniqueProvider
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue>|list<TValue> $expected
     * @param iterable<TKey,TValue> $array
     */
    public function testUnique(array $expected, iterable $array, bool $preserveKeys = false): void
    {
        $this->assertSame($expected, Arr::unique($array, $preserveKeys));
    }

    /**
     * @return array<array{mixed[],iterable<mixed>}>
     */
    public static function uniqueProvider(): array
    {
        $a = new stdClass();
        $b = new stdClass();

        return [
            [
                [],
                [],
            ],
            [
                [$a, $b],
                [$a, $a, $b, $b],
            ],
            [
                [['foo' => 'bar'], '', null, $a, 3.14, '0'],
                [['foo' => 'bar'], '', ['foo' => 'bar'], '', '', null, $a, 3.14, null, '0', 3.14, 3.14, '0', $a, '0', ['foo' => 'bar'], $a, null],
            ],
            [
                [['foo' => 'bar'], '', 5 => null, 6 => $a, 7 => 3.14, 9 => '0'],
                [['foo' => 'bar'], '', ['foo' => 'bar'], '', '', null, $a, 3.14, null, '0', 3.14, 3.14, '0', $a, '0', ['foo' => 'bar'], $a, null],
                true,
            ],
        ];
    }

    public function testUnset(): void
    {
        $array = ['foo' => 'bar', 'baz' => 'qux'];
        $this->assertSame(['foo' => 'bar'], Arr::unset($array, 'baz'));
        $this->assertSame($array, Arr::unset($array, 'bar'));
    }

    /**
     * @dataProvider unshiftProvider
     *
     * @param mixed[] $expected
     * @param mixed[] $array
     * @param mixed ...$values
     */
    public function testUnshift(array $expected, array $array, ...$values): void
    {
        $this->assertSame($expected, Arr::unshift($array, ...$values));
    }

    /**
     * @return array<array{mixed[],mixed[],...}>
     */
    public static function unshiftProvider(): array
    {
        return [
            [['d', 'e', 'a', 'b', 'c'], ['a', 'b', 'c'], 'd', 'e'],
            [[3, 4, 0, 1, 2], [0, 1, 2], 3, 4],
            [[true, false, false, true], [false, true], true, false],
            [[false, true, true, false], [true, false], false, true],
            [[], []],
            [[null], [], null],
            [[false], [], false],
            [[0], [], 0],
            [['baz', 'qux', 'a' => 'foo', 'b' => 'bar'], ['a' => 'foo', 'b' => 'bar'], 'baz', 'qux'],
        ];
    }

    /**
     * @dataProvider unwrapProvider
     *
     * @param mixed $expected
     * @param mixed $value
     */
    public function testUnwrap($expected, $value, int $limit = -1): void
    {
        $this->assertSame($expected, Arr::unwrap($value, $limit));
    }

    /**
     * @return array<mixed[]>
     */
    public static function unwrapProvider(): array
    {
        return [
            [
                ['id' => 1],
                [[['id' => 1]]],
            ],
            [
                'nested scalar',
                ['nested scalar'],
            ],
            [
                ['nested associative' => 1],
                ['nested associative' => 1],
            ],
            [
                [1, 'links' => [2, 3]],
                [[1, 'links' => [2, 3]]],
            ],
            [
                'plain scalar',
                'plain scalar',
            ],
            [
                [[['id' => 1]]],
                [[['id' => 1]]],
                0,
            ],
            [
                [['id' => 1]],
                [[['id' => 1]]],
                1,
            ],
            [
                ['id' => 1],
                [[['id' => 1]]],
                2,
            ],
            [
                ['id' => 1],
                [[['id' => 1]]],
                3,
            ],
            [
                ['nested scalar'],
                ['nested scalar'],
                0,
            ],
            [
                'nested scalar',
                ['nested scalar'],
                1,
            ],
            [
                'nested scalar',
                ['nested scalar'],
                2,
            ],
            [
                ['nested associative' => 1],
                ['nested associative' => 1],
                0,
            ],
            [
                ['nested associative' => 1],
                ['nested associative' => 1],
                1,
            ],
            [
                [[1, 'links' => [2, 3]]],
                [[1, 'links' => [2, 3]]],
                0,
            ],
            [
                [1, 'links' => [2, 3]],
                [[1, 'links' => [2, 3]]],
                1,
            ],
            [
                [1, 'links' => [2, 3]],
                [[1, 'links' => [2, 3]]],
                2,
            ],
            [
                'plain scalar',
                'plain scalar',
                0,
            ],
            [
                'plain scalar',
                'plain scalar',
                1,
            ],
        ];
    }

    /**
     * @dataProvider upperProvider
     *
     * @param string[] $expected
     * @param array<int|float|string|bool|Stringable|null> $array
     */
    public function testUpper($expected, $array): void
    {
        $this->assertSame($expected, Arr::upper($array));
    }

    /**
     * @return array<array{string[],array<int|float|string|bool|Stringable|null>}>
     */
    public static function upperProvider(): array
    {
        return [
            [
                [],
                [],
            ],
            [
                [
                    '0',
                    '3.14',
                    'null' => '',
                    '1',
                    'false' => '',
                    'string' => 'TITLE',
                    Stringable::class => "I'M BATMAN.",
                ],
                [
                    0,
                    3.14,
                    'null' => null,
                    true,
                    'false' => false,
                    'string' => 'title',
                    Stringable::class => new class implements Stringable {
                        public function __toString()
                        {
                            return "I'm Batman.";
                        }
                    },
                ],
            ],
        ];
    }

    /**
     * @dataProvider snakeCaseProvider
     *
     * @param string[] $expected
     * @param array<int|float|string|bool|Stringable|null> $array
     */
    public function testSnakeCase($expected, $array): void
    {
        $this->assertSame($expected, Arr::snakeCase($array));
    }

    /**
     * @return array<array{string[],array<int|float|string|bool|Stringable|null>}>
     */
    public static function snakeCaseProvider(): array
    {
        return [
            [
                [],
                [],
            ],
            [
                [
                    '0',
                    '3_14',
                    'null' => '',
                    '1',
                    'false' => '',
                    'string' => 'hello_world',
                    Stringable::class => 'i_m_batman',
                ],
                [
                    0,
                    3.14,
                    'null' => null,
                    true,
                    'false' => false,
                    'string' => 'Hello, world!',
                    Stringable::class => new class implements Stringable {
                        public function __toString()
                        {
                            return "I'm Batman.";
                        }
                    },
                ],
            ],
        ];
    }

    /**
     * @dataProvider whereNotEmptyProvider
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool|Stringable|null
     *
     * @param array<TKey,TValue> $expected
     * @param iterable<TKey,TValue> $array
     */
    public function testWhereNotEmpty(array $expected, iterable $array): void
    {
        $this->assertSame($expected, Arr::whereNotEmpty($array));
    }

    /**
     * @return array<array{mixed[],mixed[]}>
     */
    public static function whereNotEmptyProvider(): array
    {
        $a = new class { public function __toString() { return 'a'; } };
        $b = new class { public function __toString() { return ''; } };

        return [
            [
                [],
                [],
            ],
            [
                [],
                [null, '', false, $b],
            ],
            [
                [3 => 0, 4 => 'a', 5 => $a],
                [null, '', false, 0, 'a', $a, $b],
            ],
        ];
    }

    /**
     * @dataProvider whereNotNullProvider
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $expected
     * @param iterable<TKey,TValue|null> $array
     */
    public function testWhereNotNull(array $expected, iterable $array): void
    {
        $this->assertSame($expected, Arr::whereNotNull($array));
    }

    /**
     * @return array<array{mixed[],mixed[]}>
     */
    public static function whereNotNullProvider(): array
    {
        $a = new stdClass();

        return [
            [
                [],
                [],
            ],
            [
                [],
                [null],
            ],
            [
                [1 => '', 2 => false, 3 => 0, 4 => 'a', 5 => $a],
                [null, '', false, 0, 'a', $a],
            ],
        ];
    }

    /**
     * @dataProvider withProvider
     *
     * @template TKey of array-key
     * @template TValue
     * @template T
     *
     * @param mixed $expected
     * @param iterable<TKey,TValue> $array
     * @param callable(T, TValue, TKey): T $callback
     * @param T $value
     */
    public function testWith($expected, iterable $array, callable $callback, $value): void
    {
        $this->assertSame($expected, Arr::with($array, $callback, $value));
    }

    /**
     * @return array<array{mixed,iterable<mixed>,callable,mixed}>
     */
    public static function withProvider(): array
    {
        $a = [1, 2, 3, 4, 5];

        return [
            [
                15,
                $a,
                fn($carry, $value) => $carry += $value,
                0,
            ],
            [
                1200,
                $a,
                fn($carry, $value) => $carry *= $value,
                10,
            ],
            [
                null,
                [],
                fn($carry, $value) => $carry += $value,
                null,
            ],
        ];
    }

    /**
     * @dataProvider wrapProvider
     *
     * @param mixed[] $expected
     * @param mixed $value
     */
    public function testWrap(array $expected, $value): void
    {
        $this->assertSame($expected, Arr::wrap($value));
    }

    /**
     * @return array<array{mixed[],mixed}>
     */
    public static function wrapProvider(): array
    {
        return [
            [[], null],
            [[0], 0],
            [[0], [0]],
            [[1], 1],
            [[1], [1]],
            [[false], false],
            [[false], [false]],
            [[true], true],
            [[true], [true]],
            [[''], ''],
            [[''], ['']],
            [['a'], 'a'],
            [['a'], ['a']],
            [['a', 'b'], ['a', 'b']],
            [[7 => 'a', 1 => 'b'], [7 => 'a', 1 => 'b']],
            [['a' => 'a', 'b' => 'b'], ['a' => 'a', 'b' => 'b']],
        ];
    }
}
