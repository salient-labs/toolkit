<?php

declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Facade\Convert;

final class ConversionsTest extends \Lkrms\Tests\TestCase
{
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
        $this->assertEquals(["id" => 27, "name" => "B"], Convert::iterableToItem($iterator, "id", 27));
        $this->assertEquals(["id" => 8, "name" => "C"], Convert::iterableToItem($iterator, "id", 8));
        $this->assertEquals(["id" => 8, "name" => "D"], Convert::iterableToItem($iterator, "id", 8));
        $this->assertEquals(["id" => 21, "name" => "F"], Convert::iterableToItem($iterator, "id", 21));
        $this->assertEquals(null, Convert::iterableToItem($iterator, "id", 8));

        $iterator = $iteratorFactory();
        $this->assertEquals(["id" => 10, "name" => "A"], Convert::iterableToItem($iterator, "id", 10));

    }

}
