<?php declare(strict_types=1);

namespace Salient\Tests\Collection;

use Salient\Collection\Collection;
use Salient\Tests\TestCase;
use OutOfRangeException;

/**
 * @covers \Salient\Collection\Collection
 * @covers \Salient\Collection\CollectionTrait
 * @covers \Salient\Collection\ReadOnlyCollectionTrait
 */
final class CollectionTest extends TestCase
{
    public function testCollection(): void
    {
        /** @var Collection<array-key,MyComparableClass> */
        $collection = new Collection();

        $e0 = new MyComparableClass('delta');
        $e1 = new MyComparableClass('november');
        $e2 = new MyComparableClass('charlie');

        $collection[] = $e0;
        $collection[] = $e1;
        $collection[] = $e2;

        $this->assertTrue(isset($collection[1]));
        $this->assertSame($e1, $collection[1]);
        unset($collection[1]);
        $this->assertFalse(isset($collection[1]));
        $collection['n'] = $e1;
        $this->assertSame([0 => $e0, 2 => $e2, 'n' => $e1], $collection->all());
        $this->assertTrue($collection->has('n'));
        $this->assertSame($e1, $collection->get('n'));
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
            $callback = function ($item, $next, $prev) use (&$arr, &$arrNext, &$arrPrev) {
                $arr[] = $item;
                $arrNext[] = $next;
                $arrPrev[] = $prev;
            }
        );
        $this->assertSame($collection, $coll);
        ($assertSameCallbackArgs = function () use (&$arr, &$arrNext, &$arrPrev, $e0, $e1, $e2) {
            $this->assertSame([$e0, $e2, $e1], $arr);
            $this->assertSame([$e2, $e1, null], $arrNext);
            $this->assertSame([null, $e0, $e2], $arrPrev);
        })();

        $arr = $arrNext = $arrPrev = [];
        $coll = $collection->map(
            function (MyComparableClass $item, $next, $prev) use ($callback) {
                $callback($item, $next, $prev);
                return $item;
            }
        );
        $this->assertSame($collection, $coll);
        $assertSameCallbackArgs();

        $coll = $collection->map(fn(MyComparableClass $item) => new MyComparableClass($item->Name . '-2'));
        $this->assertNotSame($collection, $coll);
        $this->assertSame(
            [0 => 'delta-2', 2 => 'charlie-2', 'n' => 'november-2'],
            array_map(fn($item) => $item->Name, $coll->all()),
        );

        $arr = $arrNext = $arrPrev = [];
        $coll = $collection->filter(
            function ($item, $next, $prev) use ($callback) {
                $callback($item, $next, $prev);
                return $prev !== null;
            }
        );
        $this->assertSame([2 => $e2, 'n' => $e1], $coll->all());
        $assertSameCallbackArgs();

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
            function ($item, $next, $prev) use ($callback) {
                $callback($item, $next, $prev);
                return !$next;
            }
        );
        $this->assertSame($e1, $found);
        $assertSameCallbackArgs();

        $this->assertSame($e2, $collection->find(fn(MyComparableClass $item) => $item->Name === 'charlie'));
        $this->assertSame('n', $collection->find(fn(MyComparableClass $item) => $item->Name === 'november', Collection::CALLBACK_USE_VALUE | Collection::FIND_KEY));
        $this->assertNull($collection->find(fn() => false));

        $slice = $collection->slice(1, 1);
        $this->assertSame([2 => $e2], $slice->toArray());

        $e3 = new MyComparableClass('charlie');
        $e4 = new MyComparableClass('echo');
        $this->assertTrue($collection->hasValue($e3));
        $this->assertFalse($collection->hasValue($e3, true));
        $this->assertSame(2, $collection->keyOf($e3));
        $this->assertNull($collection->keyOf($e3, true));
        $this->assertSame(2, $collection->keyOf($e2, true));
        $this->assertSame($e2, $collection->firstOf($e3));
        $this->assertNull($collection->firstOf($e4));

        $first = null;
        $this->assertSame([2 => $e2, 'n' => $e1], $collection->shift($first)->all());
        $this->assertSame($e0, $first);

        $last = null;
        $this->assertSame([0 => $e0, 2 => $e2], $collection->pop($last)->all());
        $this->assertSame($e1, $last);

        $coll = $collection->set('n', $e4);
        $this->assertSame([0 => $e0, 2 => $e2, 'n' => $e4], $coll->all());
        $coll = $coll->unset(0);
        $this->assertSame([2 => $e2, 'n' => $e4], $coll->all());
        $coll = $coll->merge(['i' => $e0, 'n' => $e1, 11 => $e4]);
        $this->assertSame([2 => $e2, 'n' => $e1, 'i' => $e0, 3 => $e4], $coll->all());
    }

    public function testEmptyCollection(): void
    {
        /** @var Collection<array-key,MyComparableClass> */
        $collection = new Collection();

        /** @disregard P1008 */
        $coll = $collection->pop($last);
        $this->assertSame($collection, $coll);
        /** @disregard P1008 */
        $this->assertNull($last);

        /** @disregard P1008 */
        $coll = $collection->shift($first);
        $this->assertSame($collection, $coll);
        /** @disregard P1008 */
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

        $coll = $collection->map(fn(MyComparableClass $item) => $item);
        $this->assertSame($collection, $coll);

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

        $e0 = new MyComparableClass('foo');
        $this->assertFalse($collection->hasValue($e0));
        $this->assertFalse($collection->hasValue($e0, true));
        $this->assertNull($collection->keyOf($e0));
        $this->assertNull($collection->keyOf($e0, true));
        $this->assertNull($collection->firstOf($e0));
        $this->assertSame([], $collection->all());
        $this->assertSame([], $collection->toArray());
        $this->assertNull($collection->first());
        $this->assertNull($collection->last());
        $this->assertNull($collection->nth(1));
        $this->assertFalse($collection->has('foo'));

        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Item not found: foo');
        $collection->get('foo');
    }
}
