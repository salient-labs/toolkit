<?php declare(strict_types=1);

namespace Salient\Tests\Iterator;

use Salient\Iterator\IterableIterator;
use Salient\Tests\TestCase;
use ArrayIterator;
use Generator;
use NoRewindIterator;
use stdClass;

/**
 * @covers \Salient\Iterator\IterableIterator
 */
final class IterableIteratorTest extends TestCase
{
    /**
     * @dataProvider nextWithValueProvider
     *
     * @param mixed $expected
     * @param mixed[] $array
     * @param array-key $key
     * @param mixed $value
     */
    public function testNextWithValue(
        $expected,
        array $array,
        $key,
        $value,
        int $runs = 1,
        bool $strict = false
    ): void {
        $iterator = new IterableIterator(new NoRewindIterator(new ArrayIterator($array)));
        while ($runs > 1) {
            $iterator->nextWithValue($key, $value, $strict);
            $runs--;
        }
        $this->assertSame($expected, $iterator->nextWithValue($key, $value, $strict));
    }

    /**
     * @return array<array{mixed,mixed[],array-key,mixed,4?:int,5?:bool}>
     */
    public static function nextWithValueProvider(): array
    {
        $data = [
            ['id' => 10, 'name' => 'foo'],
            ['id' => 27, 'name' => 'bar'],
            ['id' => 8, 'name' => 'qux'],
            ['id' => 71, 'name' => 'qux'],
            ['id' => 72, 'name' => 'quux'],
            ['id' => 21, 'name' => 'quuux'],
        ];

        return [
            [
                ['id' => 10, 'name' => 'foo'],
                $data,
                'id',
                10,
            ],
            [
                null,
                $data,
                'id',
                10,
                2,
            ],
            [
                ['id' => 27, 'name' => 'bar'],
                $data,
                'id',
                27,
            ],
            [
                ['id' => 8, 'name' => 'qux'],
                $data,
                'name',
                'qux',
            ],
            [
                ['id' => 71, 'name' => 'qux'],
                $data,
                'name',
                'qux',
                2,
            ],
            [
                null,
                $data,
                'name',
                'qux',
                3,
            ],
            [
                ['id' => 21, 'name' => 'quuux'],
                $data,
                'id',
                21,
            ],
            [
                null,
                $data,
                'id',
                21,
                2,
            ],
        ];
    }

    public function testFromValues(): void
    {
        $iterator = IterableIterator::fromValues([
            'foo' => 'bar',
            'baz',
        ]);
        $this->assertSame(['bar', 'baz'], $iterator->toArray());

        $iterator = IterableIterator::fromValues($this->getGenerator());
        $this->assertSame(['bar', 'baz', 'qux', 'quux'], $iterator->toArray());
    }

    /**
     * @return Generator<mixed,string>
     */
    private function getGenerator(): Generator
    {
        yield 'foo' => 'bar';
        yield 'baz';
        yield new stdClass() => 'qux';
        yield 'quux';
    }
}
