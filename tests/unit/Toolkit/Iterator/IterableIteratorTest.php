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
     * @dataProvider getFirstWithProvider
     *
     * @param mixed $expected
     * @param array-key|null $expectedKey
     * @param mixed[] $array
     * @param array-key $key
     * @param mixed $value
     */
    public function testGetFirstWith(
        $expected,
        $expectedKey,
        array $array,
        $key,
        $value,
        int $runs = 1,
        bool $strict = false
    ): void {
        $iterator = new IterableIterator(new NoRewindIterator(new ArrayIterator($array)));
        while ($runs > 1) {
            $iterator->getFirstWith($key, $value, $strict);
            $iterator->next();
            $runs--;
        }
        $this->assertSame($expected, $iterator->getFirstWith($key, $value, $strict));
        $this->assertSame($expected, $iterator->current());
        $this->assertSame($expectedKey, $iterator->key());
    }

    /**
     * @return array<array{mixed,array-key|null,mixed[],array-key,mixed,5?:int,6?:bool}>
     */
    public static function getFirstWithProvider(): array
    {
        $data = [
            ['id' => 10, 'name' => 'foo'],
            ['id' => 27, 'name' => 'bar'],
            ['id' => 8, 'name' => 'qux'],
            ['id' => 71, 'name' => 'qux'],
            ['id' => 72, 'name' => 'quux'],
            'foo' => ['id' => 21, 'name' => 'quuux'],
        ];

        return [
            [
                ['id' => 10, 'name' => 'foo'],
                0,
                $data,
                'id',
                10,
            ],
            [
                null,
                null,
                $data,
                'id',
                10,
                2,
            ],
            [
                ['id' => 27, 'name' => 'bar'],
                1,
                $data,
                'id',
                27,
            ],
            [
                ['id' => 8, 'name' => 'qux'],
                2,
                $data,
                'name',
                'qux',
            ],
            [
                ['id' => 71, 'name' => 'qux'],
                3,
                $data,
                'name',
                'qux',
                2,
            ],
            [
                null,
                null,
                $data,
                'name',
                'qux',
                3,
            ],
            [
                ['id' => 21, 'name' => 'quuux'],
                'foo',
                $data,
                'id',
                21,
            ],
            [
                null,
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
