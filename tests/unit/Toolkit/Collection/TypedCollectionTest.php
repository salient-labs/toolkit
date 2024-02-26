<?php declare(strict_types=1);

namespace Salient\Tests\Collection;

use Salient\Tests\Collection\TypedCollection\MyClass;
use Salient\Tests\Collection\TypedCollection\MyCollection;
use Salient\Tests\TestCase;

final class TypedCollectionTest extends TestCase
{
    public function testTypedCollection(): void
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
        $this->assertSame([0 => $e0, 2 => $e2, 'n' => $e1], $collection->all());
        $sorted = $collection->sort();
        $sorted2 = $sorted->sort();
        $this->assertNotSame($sorted, $collection);
        $this->assertSame($sorted2, $sorted);
        $this->assertSame(['n' => $e1, 2 => $e2, 0 => $e0], $sorted->all());
        $reversed = $sorted->reverse();
        $this->assertNotSame($reversed, $sorted);
        $this->assertSame([0 => $e0, 2 => $e2, 'n' => $e1], $reversed->all());
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
        $this->assertSame([2 => $e2, 'n' => $e1], $coll->all());
        $this->assertSame([$e0, $e2, $e1], $arr);
        $this->assertSame([$e2, $e1, null], $arrNext);
        $this->assertSame([null, $e0, $e2], $arrPrev);

        $coll = $collection->only([0, 71, 'n']);
        $this->assertSame([0 => $e0, 'n' => $e1], $coll->all());
        $this->assertSame($coll, $coll->only([0, 71, 'n']));

        $coll = $collection->onlyIn([2 => true, 'm' => true]);
        $this->assertSame([2 => $e2], $coll->all());
        $this->assertSame($coll, $coll->onlyIn([2 => true, 'm' => true]));

        $coll = $collection->except([0, 71, 'n']);
        $this->assertSame([2 => $e2], $coll->all());
        $this->assertSame($coll, $coll->except([0, 71, 'n']));

        $coll = $collection->exceptIn([0 => true, 1 => true, 2 => true]);
        $this->assertSame(['n' => $e1], $coll->all());
        $this->assertSame($coll, $coll->exceptIn([0 => true, 1 => true, 2 => true]));

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

        $this->assertNull($collection->find(fn() => false));

        $slice = $collection->slice(1, 1);
        $this->assertSame([2 => $e2], $slice->toArray());

        $e3 = new MyClass('charlie');
        $e4 = new MyClass('echo');
        $this->assertTrue($collection->has($e3));
        $this->assertFalse($collection->has($e3, true));
        $this->assertSame(2, $collection->keyOf($e3));
        $this->assertNull($collection->keyOf($e3, true));
        $this->assertSame(2, $collection->keyOf($e2, true));
        $this->assertSame($e2, $collection->get($e3));
        $this->assertNull($collection->get($e4));

        $collection->set('n', $e4);
        $this->assertSame([0 => $e0, 2 => $e2, 'n' => $e4], $collection->all());
        $collection->unset(0);
        $this->assertSame([2 => $e2, 'n' => $e4], $collection->all());
        $collection->merge(['i' => $e0, 'n' => $e1, 11 => $e4]);
        $this->assertSame([2 => $e2, 'n' => $e1, 'i' => $e0, 3 => $e4], $collection->all());
    }

    public function testEmptyTypedCollection(): void
    {
        $collection = new MyCollection();

        $coll = $collection->pop($last);
        $this->assertSame($collection, $coll);
        $this->assertNull($last);

        $coll = $collection->shift($first);
        $this->assertSame($collection, $coll);
        $this->assertNull($first);

        $coll = $collection->sort();
        $this->assertSame($collection, $coll);

        $coll = $collection->reverse();
        $this->assertSame($collection, $coll);

        $count = 0;
        $collection->forEach(
            function () use (&$count) {
                $count++;
            }
        );
        $this->assertSame($coll, $collection);
        $this->assertSame(0, $count);

        $coll = $collection->filter(fn() => true);
        $this->assertSame($collection, $coll);

        $coll = $collection->only([]);
        $this->assertSame($collection, $coll);

        $coll = $collection->onlyIn([]);
        $this->assertSame($collection, $coll);

        $coll = $collection->except([]);
        $this->assertSame($collection, $coll);

        $coll = $collection->exceptIn([]);
        $this->assertSame($collection, $coll);

        $this->assertNull($collection->find(fn() => true));

        $coll = $collection->slice(0);
        $this->assertSame($collection, $coll);

        $coll = $collection->merge([]);
        $this->assertSame($collection, $coll);

        $e0 = new MyClass('foo');
        $this->assertFalse($collection->has($e0));
        $this->assertFalse($collection->has($e0, true));
        $this->assertNull($collection->keyOf($e0));
        $this->assertNull($collection->keyOf($e0, true));
        $this->assertNull($collection->get($e0));
        $this->assertSame([], $collection->all());
        $this->assertSame([], $collection->toArray());
        $this->assertNull($collection->first());
        $this->assertNull($collection->last());
        $this->assertNull($collection->nth(1));
    }
}
