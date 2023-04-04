<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept;

use Lkrms\Tests\Concept\TypedCollection\MyClass;
use Lkrms\Tests\Concept\TypedCollection\MyCollection;
use UnexpectedValueException;

final class TypedCollectionTest extends \Lkrms\Tests\TestCase
{
    public function testTypedCollection()
    {
        $collection = new MyCollection();

        $e0 = new MyClass('delta');
        $e1 = new MyClass('november');
        $e2 = new MyClass('charlie');

        $collection[] = $e0;
        $collection[] = $e1;
        $collection[] = $e2;

        $this->assertTrue(isset($collection[1]));
        unset($collection[1]);
        $this->assertFalse(isset($collection[1]));
        $collection['n'] = $e1;
        $this->assertSame([0 => $e0, 2 => $e2, 'n' => $e1], $collection->toArray());
        $this->assertSame([$e0, $e2, $e1], $collection->toArray(false));
        $sorted = $collection->sort();
        $this->assertNotSame($sorted, $collection);
        $this->assertSame(['n' => $e1, 2 => $e2, 0 => $e0], $sorted->toArray());
        $this->assertSame([$e1, $e2, $e0], $collection->sort(false)->toArray());
        $reversed = $sorted->reverse();
        $this->assertNotSame($reversed, $sorted);
        $this->assertSame([0 => $e0, 2 => $e2, 'n' => $e1], $reversed->toArray());
        $this->assertSame([$e0, $e2, $e1], $sorted->reverse(false)->toArray());
        $this->assertCount(3, $collection);

        foreach ($collection as $key => $value) {
            $arr[$key] = $value;
        }
        $this->assertSame([0 => $e0, 2 => $e2, 'n' => $e1], $arr ?? null);

        $arr = $arrNext = $arrPrev = [];
        $coll = $collection->forEach(
            function ($item, $next, $prev) use (&$arr, &$arrNext, &$arrPrev) {
                $arr[] = $item;
                $arrNext[] = $next;
                $arrPrev[] = $prev;
            }
        );
        $this->assertSame($collection, $coll);
        $this->assertSame([$e0, $e2, $e1], $arr);
        $this->assertSame([$e2, $e1, null], $arrNext);
        $this->assertSame([null, $e0, $e2], $arrPrev);

        $arr = $arrNext = $arrPrev = [];
        $coll = $collection->filter(
            function ($item, $next, $prev) use (&$arr, &$arrNext, &$arrPrev): bool {
                $arr[] = $item;
                $arrNext[] = $next;
                $arrPrev[] = $prev;

                return (bool) $prev;
            }
        );
        $this->assertSame([$e2, $e1], $coll->toArray());
        $this->assertSame([$e0, $e2, $e1], $arr);
        $this->assertSame([$e2, $e1, null], $arrNext);
        $this->assertSame([null, $e0, $e2], $arrPrev);

        $arr = $arrNext = $arrPrev = [];
        $found = $collection->find(
            function ($item, $next, $prev) use (&$arr, &$arrNext, &$arrPrev): bool {
                $arr[] = $item;
                $arrNext[] = $next;
                $arrPrev[] = $prev;

                return !$next;
            }
        );
        $this->assertSame($e1, $found);
        $this->assertSame([$e0, $e2, $e1], $arr);
        $this->assertSame([$e2, $e1, null], $arrNext);
        $this->assertSame([null, $e0, $e2], $arrPrev);

        $this->assertFalse($collection->find(fn() => false));

        $this->expectException(UnexpectedValueException::class);
        // @phpstan-ignore-next-line
        $collection[] = 'romeo';
    }
}
