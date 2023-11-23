<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Utility\Arr;

final class ArrTest extends \Lkrms\Tests\TestCase
{
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
        $object1 = new \stdClass();
        $object2 = new \stdClass();
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
     * @dataProvider implodeProvider
     *
     * @param mixed[] $array
     */
    public function testImplode(string $expected, string $separator, array $array): void
    {
        $this->assertSame($expected, Arr::implode($separator, $array));
    }

    /**
     * @return array<array{string,string,mixed[]}>
     */
    public static function implodeProvider(): array
    {
        return [
            [
                '0,a, ,-1,0,1,3.14,1',
                ',',
                ['0', '', 'a', ' ', -1, 0, 1, false, 3.14, null, true],
            ],
            [
                '0 a   -1 0 1 3.14 1',
                ' ',
                ['0', '', 'a', ' ', -1, 0, 1, false, 3.14, null, true],
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
        ];
    }

    /**
     * @dataProvider isIndexedProvider
     *
     * @param mixed $value
     */
    public function testIsIndexed(bool $expected, $value, bool $allowEmpty = false): void
    {
        $this->assertSame($expected, Arr::isIndexed($value, $allowEmpty));
    }

    /**
     * @return array<array{bool,mixed,2?:bool}>
     */
    public static function isIndexedProvider(): array
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
     * @dataProvider ofArrayKeyProvider
     *
     * @param mixed $value
     */
    public function testOfArrayKey($value, bool $expected, bool $expectedIfAllowEmpty): void
    {
        $this->assertSame($expected, Arr::ofArrayKey($value));
        $this->assertSame($expectedIfAllowEmpty, Arr::ofArrayKey($value, true));
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
            'strings' => [['a', 'b'], true, true],
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
    public function testOfInt($value, bool $expected, bool $expectedIfAllowEmpty): void
    {
        $this->assertSame($expected, Arr::ofInt($value));
        $this->assertSame($expectedIfAllowEmpty, Arr::ofInt($value, true));
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
    public function testOfString($value, bool $expected, bool $expectedIfAllowEmpty): void
    {
        $this->assertSame($expected, Arr::ofString($value));
        $this->assertSame($expectedIfAllowEmpty, Arr::ofString($value, true));
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
            'mixed #1' => [[0, 'a'], false, false],
            'mixed #2' => [[0, 1, true], false, false],
            'mixed #3' => [[0, 1, null], false, false],
            'mixed #4' => [['a', 'b', true], false, false],
            'mixed #5' => [['a', 'b', null], false, false],
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
        $object1 = new \stdClass();
        $object2 = new \stdClass();
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
     * @dataProvider popProvider
     *
     * @param mixed[] $expected
     * @param mixed $popped
     * @param mixed[] $array
     */
    public function testPop(array $expected, $popped, array $array): void
    {
        // @phpstan-ignore-next-line
        $this->assertSame($expected, Arr::pop($array, $actualShifted));
        $this->assertSame($popped, $actualShifted);
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
     * @dataProvider shiftProvider
     *
     * @param mixed[] $expected
     * @param mixed $shifted
     * @param mixed[] $array
     */
    public function testShift(array $expected, $shifted, array $array): void
    {
        // @phpstan-ignore-next-line
        $this->assertSame($expected, Arr::shift($array, $actualShifted));
        $this->assertSame($shifted, $actualShifted);
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
}
