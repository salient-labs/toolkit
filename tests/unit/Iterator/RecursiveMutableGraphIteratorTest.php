<?php declare(strict_types=1);

namespace Lkrms\Tests\Iterator;

use Lkrms\Iterator\RecursiveMutableGraphIterator;
use Lkrms\Tests\TestCase;
use RecursiveIteratorIterator;
use stdClass;

final class RecursiveMutableGraphIteratorTest extends TestCase
{
    public function testRecursion(): void
    {
        $mixed = $this->getArrayWithNestedObjectsAndArrays($a, $d1, $d2, $e, $l);
        $iterator = new RecursiveMutableGraphIterator($mixed);
        $recursiveIterator = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
        $replaceValue = ['g', 1, $d2];
        $replaceWith = [100, 101, [8, 13, 21]];
        $out = [];
        foreach ($recursiveIterator as $key => $value) {
            $out[] = [$key => $value];

            if ($key === 'l') {
                /** @var RecursiveMutableGraphIterator $iterator */
                $iterator = $recursiveIterator->getInnerIterator();
                $iterator->maybeConvertToArray();
                continue;
            }

            if (($replaceKey = array_search($value, $replaceValue, true)) !== false) {
                /** @var RecursiveMutableGraphIterator $iterator */
                $iterator = $recursiveIterator->getInnerIterator();
                $iterator->replace($replaceWith[$replaceKey]);
            }
        }

        $this->assertSame([
            [0 => $a],
            ['b' => ['c', ['d1' => $d1, 'd2' => $d2]]],
            [0 => 'c'],
            [1 => ['d1' => $d1, 'd2' => $d2]],
            ['d1' => $d1],
            ['d2' => $d2],
            // Because $d2 is replaced with [8, 13, 21]
            [0 => 8],
            [1 => 13],
            [2 => 21],
            ['e' => $e],
            ['f' => ['g', 'h', 'i']],
            [0 => 'g'],
            [1 => 'h'],
            [2 => 'i'],
            ['l' => $l],
            ['m' => 'tree'],
            ['k' => 21],
            [1 => [0, 1, 1, 2, 3, 5]],
            [0 => 0],
            [1 => 1],
            [2 => 1],
            [3 => 2],
            [4 => 3],
            [5 => 5],
        ], $out);

        $this->assertSame([
            $a,
            [0, 101, 101, 2, 3, 5],
        ], $mixed);

        $this->assertSame([
            'c',
            [
                'd1' => $d1,
                'd2' => [8, 13, 21],
            ],
        ], $mixed[0]->b);

        $this->assertSame($e, $mixed[0]->e);
        $this->assertSame([100, 'h', 'i'], $mixed[0]->e->f);
        $this->assertSame(21, $mixed[0]->k);
        $this->assertSame(['m' => 'tree'], $mixed[0]->e->l);
    }

    /**
     * @return mixed[]
     */
    private function getArrayWithNestedObjectsAndArrays(
        ?stdClass &$a = null,
        ?stdClass &$d1 = null,
        ?stdClass &$d2 = null,
        ?stdClass &$e = null,
        ?stdClass &$l = null
    ): array {
        $a = new stdClass();
        $a->b = [
            'c',
            [
                'd1' => $d1 = new stdClass(),
                'd2' => $d2 = new stdClass(),
            ],
        ];
        $a->e = $e = new stdClass();
        $a->e->f = ['g', 'h', 'i'];
        $j = [
            $a,
            [0, 1, 1, 2, 3, 5],
        ];
        $a->k = 21;
        $e->l = $l = new stdClass();
        $e->l->m = 'tree';

        return $j;
    }
}
