<?php

declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Facade\Convert;
use UnexpectedValueException;

final class ConversionsTest extends \Lkrms\Tests\TestCase
{
    public function testFlatten()
    {
        $data = [
            [[['id' => 1]]],
            ['nested scalar'],
            ['nested associative' => 1],
            [[1, 'links' => [2, 3]]],
            'plain scalar',
        ];

        $flattened = array_map(fn($value) => Convert::flatten($value), $data);

        $this->assertSame([
            ['id' => 1],
            'nested scalar',
            ['nested associative' => 1],
            [1, 'links' => [2, 3]],
            'plain scalar',
        ], $flattened);

    }

    public function testArrayKeyToOffset()
    {
        $data = [
            "a" => "value0",
            "b" => "value1",
            "A" => "value2",
            "B" => "value3",
        ];

        $this->assertSame(0, Convert::arrayKeyToOffset("a", $data));
        $this->assertSame(1, Convert::arrayKeyToOffset("b", $data));
        $this->assertSame(2, Convert::arrayKeyToOffset("A", $data));
        $this->assertSame(3, Convert::arrayKeyToOffset("B", $data));
        $this->assertNull(Convert::arrayKeyToOffset("c", $data));

    }

    public function testArraySpliceByKey()
    {
        $data1 = $data2 = $data3 = $data4 = $data5 = [
            "a" => "value0",
            "b" => "value1",
            "A" => "value2",
            "B" => "value3",
        ];

        $slice = Convert::getInstance()->arraySpliceAtKey($data1, "b");
        $this->assertSame([
            "b" => "value1",
            "A" => "value2",
            "B" => "value3",
        ], $slice);
        $this->assertSame([
            "a" => "value0",
        ], $data1);

        $slice = Convert::getInstance()->arraySpliceAtKey($data2, "A", 1, ["A2" => 10]);
        $this->assertSame([
            "A" => "value2",
        ], $slice);
        $this->assertSame([
            "a"  => "value0",
            "b"  => "value1",
            "A2" => 10,
            "B"  => "value3",
        ], $data2);

        $slice = Convert::getInstance()->arraySpliceAtKey($data3, "B", 0, ["a" => 20]);
        $this->assertSame([], $slice);
        $this->assertSame([
            "a" => 20,
            "b" => "value1",
            "A" => "value2",
            "B" => "value3",
        ], $data3);

        $slice = Convert::getInstance()->arraySpliceAtKey($data4, "B", 0, ["A2" => 10]);
        $this->assertSame([], $slice);
        $this->assertSame([
            "a"  => "value0",
            "b"  => "value1",
            "A"  => "value2",
            "A2" => 10,
            "B"  => "value3",
        ], $data4);

        $this->expectException(UnexpectedValueException::class);
        $slice = Convert::getInstance()->arraySpliceAtKey($data5, "c", 2);

    }

    public function testRenameArrayKey()
    {
        $data = [
            "a" => "value0",
            "b" => "value1",
            "A" => "value2",
            "B" => "value3",
        ];

        $this->assertSame([
            "a"   => "value0",
            "b_2" => "value1",
            "A"   => "value2",
            "B"   => "value3",
        ], Convert::renameArrayKey("b", "b_2", $data));

        $this->assertSame([
            "a" => "value0",
            "b" => "value1",
            "A" => "value2",
            0   => "value3",
        ], Convert::renameArrayKey("B", 0, $data));

        $this->expectException(UnexpectedValueException::class);
        $slice = Convert::renameArrayKey("c", 2, $data);

    }

    public function testIterableToValue()
    {
        $data = [
            [
                "id"   => 10,
                "name" => "A",
            ],
            [
                "id"   => 27,
                "name" => "B",
            ],
            [
                "id"   => 8,
                "name" => "C",
            ],
            [
                "id"   => 8,
                "name" => "D",
            ],
            [
                "id"   => 72,
                "name" => "E",
            ],
            [
                "id"   => 21,
                "name" => "F",
            ],
        ];

        $iteratorFactory = function () use ($data)
        {
            foreach ($data as $record)
            {
                yield $record;
            }
        };

        $iterator = $iteratorFactory();
        $this->assertSame(["id" => 27, "name" => "B"], Convert::iterableToItem($iterator, "id", 27));
        $this->assertSame(["id" => 8, "name" => "C"], Convert::iterableToItem($iterator, "id", 8));
        $this->assertSame(["id" => 8, "name" => "D"], Convert::iterableToItem($iterator, "id", 8));
        $this->assertSame(["id" => 21, "name" => "F"], Convert::iterableToItem($iterator, "id", 21));
        $this->assertSame(false, Convert::iterableToItem($iterator, "id", 8));

        $iterator = $iteratorFactory();
        $this->assertSame(["id" => 10, "name" => "A"], Convert::iterableToItem($iterator, "id", 10));

    }

}
