<?php declare(strict_types=1);

namespace Lkrms\Tests\Iterator;

use Salient\Iterator\RecursiveCallbackIterator;
use Salient\Iterator\RecursiveGraphIterator;
use Salient\Tests\TestCase;
use DateTimeImmutable;
use DateTimeInterface;
use Iterator;
use RecursiveIteratorIterator;
use stdClass;

final class RecursiveCallbackIteratorTest extends TestCase
{
    public function testRecursion(): void
    {
        $mixed = $this->getArrayWithNestedObjectsAndArrays($a, $d1, $d2, $e);
        $iterator = new RecursiveGraphIterator($mixed);
        $iterator = new RecursiveCallbackIterator(
            $iterator,
            fn($value): bool =>
                !($value instanceof DateTimeInterface)
        );
        $selfFirst = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
        $leavesOnly = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::LEAVES_ONLY);

        $this->assertSame([
            [0 => $a],
            ['b' => ['c', ['d1' => $d1, 'd2' => $d2]]],
            [0 => 'c'],
            [1 => ['d1' => $d1, 'd2' => $d2]],
            ['d1' => $d1],
            ['d2' => $d2],
            ['e' => $e],
            ['f' => ['g', 'h', 'i']],
            [0 => 'g'],
            [1 => 'h'],
            [2 => 'i'],
            ['k' => 21],
            [1 => [0, 1, 1, 2, 3, 5]],
            [0 => 0],
            [1 => 1],
            [2 => 1],
            [3 => 2],
            [4 => 3],
            [5 => 5],
        ], $this->iteratorToArray($selfFirst));

        $this->assertSame([
            [0 => 'c'],
            ['d1' => $d1],
            ['d2' => $d2],
            [0 => 'g'],
            [1 => 'h'],
            [2 => 'i'],
            ['k' => 21],
            [0 => 0],
            [1 => 1],
            [2 => 1],
            [3 => 2],
            [4 => 3],
            [5 => 5],
        ], $this->iteratorToArray($leavesOnly));
    }

    /**
     * @return mixed[]
     */
    private function getArrayWithNestedObjectsAndArrays(
        ?stdClass &$a = null,
        ?DateTimeImmutable &$d1 = null,
        ?DateTimeImmutable &$d2 = null,
        ?stdClass &$e = null
    ): array {
        $a = new stdClass();
        $a->b = [
            'c',
            [
                'd1' => $d1 = new DateTimeImmutable(),
                'd2' => $d2 = new DateTimeImmutable(),
            ],
        ];
        $a->e = $e = new stdClass();
        $a->e->f = ['g', 'h', 'i'];
        $j = [
            $a,
            [0, 1, 1, 2, 3, 5],
        ];
        $a->k = 21;

        return $j;
    }

    /**
     * @param Iterator<array-key,mixed> $iterator
     * @return array<array-key,mixed>
     */
    private function iteratorToArray(Iterator $iterator): array
    {
        foreach ($iterator as $key => $value) {
            $out[] = [$key => $value];
        }
        return $out ?? [];
    }
}
