<?php declare(strict_types=1);

namespace Salient\Tests\Collection;

use Salient\Collection\ListCollection;
use Salient\Tests\TestCase;
use OutOfRangeException;

/**
 * @covers \Salient\Collection\ListCollection
 * @covers \Salient\Collection\ListCollectionTrait
 * @covers \Salient\Collection\CollectionTrait
 * @covers \Salient\Collection\ReadOnlyCollectionTrait
 */
final class ListCollectionTest extends TestCase
{
    public function testListCollection(): void
    {
        /** @var ListCollection<MyComparableClass> */
        $list = new ListCollection();

        $e0 = new MyComparableClass('delta');
        $e1 = new MyComparableClass('charlie');
        $e2 = new MyComparableClass('november');

        $list[] = $e0;
        $list[] = $e1;
        $list[] = $e2;

        $this->assertTrue(isset($list[2]));
        $this->assertSame($e2, $list[2]);
        unset($list[2]);
        $this->assertFalse(isset($list[2]));
        $list[2] = $e2;
        $this->assertSame([$e0, $e1, $e2], $list->all());
        $this->assertTrue($list->has(2));
        $this->assertSame($e2, $list->get(2));
        $sorted = $list->sort();
        $sorted2 = $sorted->sort();
        $this->assertNotSame($sorted, $list);
        $this->assertSame($sorted2, $sorted);
        $this->assertSame([$e2, $e1, $e0], $sorted->all());
        $reversed = $sorted->reverse();
        $this->assertNotSame($reversed, $sorted);
        $this->assertSame([$e0, $e1, $e2], $reversed->all());
        $this->assertCount(3, $list);

        foreach ($list as $key => $value) {
            $arr[$key] = $value;
        }
        $this->assertSame([$e0, $e1, $e2], $arr ?? null);

        $arr = $arrNext = $arrPrev = [];
        $l = $list->forEach(
            $callback = function ($item, $next, $prev) use (&$arr, &$arrNext, &$arrPrev) {
                $arr[] = $item;
                $arrNext[] = $next;
                $arrPrev[] = $prev;
            }
        );
        $this->assertSame($list, $l);
        ($assertSameCallbackArgs = function () use (&$arr, &$arrNext, &$arrPrev, $e0, $e1, $e2) {
            $this->assertSame([$e0, $e1, $e2], $arr);
            $this->assertSame([$e1, $e2, null], $arrNext);
            $this->assertSame([null, $e0, $e1], $arrPrev);
        })();

        $arr = $arrNext = $arrPrev = [];
        $l = $list->map(
            function (MyComparableClass $item, $next, $prev) use ($callback) {
                $callback($item, $next, $prev);
                return $item;
            }
        );
        $this->assertSame($list, $l);
        $assertSameCallbackArgs();

        $l = $list->map(fn(MyComparableClass $item) => new MyComparableClass($item->Name . '-2'));
        $this->assertNotSame($list, $l);
        $this->assertSame(
            ['delta-2', 'charlie-2', 'november-2'],
            array_map(fn(MyComparableClass $item) => $item->Name, $l->all()),
        );

        $arr = $arrNext = $arrPrev = [];
        $l = $list->filter(
            function ($item, $next, $prev) use ($callback) {
                $callback($item, $next, $prev);
                return $prev !== null;
            }
        );
        $this->assertSame([$e1, $e2], $l->all());
        $assertSameCallbackArgs();

        $l = $list->only([2, 3, 4]);
        $this->assertSame([$e2], $l->all());
        $this->assertSame($l, $l->only([0, 1, 2]));

        $l = $list->onlyIn([0 => true, 1 => true]);
        $this->assertSame([$e0, $e1], $l->all());
        $this->assertSame($l, $l->onlyIn([0 => true, 1 => true]));

        $l = $list->except([2, 3, 4]);
        $this->assertSame([$e0, $e1], $l->all());
        $this->assertSame($l, $l->except([2, 3, 4]));

        $l = $list->exceptIn([0 => true, 1 => true]);
        $this->assertSame([$e2], $l->all());
        $this->assertSame($l, $l->exceptIn([1 => true, 2 => true]));

        $arr = $arrNext = $arrPrev = [];
        $found = $list->find(
            function ($item, $next, $prev) use ($callback) {
                $callback($item, $next, $prev);
                return !$next;
            }
        );
        $this->assertSame($e2, $found);
        $assertSameCallbackArgs();

        $this->assertSame($e1, $list->find(fn(MyComparableClass $item) => $item->Name === 'charlie'));
        $this->assertSame(0, $list->find(fn(MyComparableClass $item) => $item->Name === 'delta', ListCollection::CALLBACK_USE_VALUE | ListCollection::FIND_KEY));
        $this->assertNull($list->find(fn() => false));

        $slice = $list->slice(1, 1);
        $this->assertSame([$e1], $slice->toArray());

        $e3 = new MyComparableClass('charlie');
        $e4 = new MyComparableClass('echo');
        $this->assertTrue($list->hasValue($e3));
        $this->assertFalse($list->hasValue($e3, true));
        $this->assertSame(1, $list->keyOf($e3));
        $this->assertNull($list->keyOf($e3, true));
        $this->assertSame(1, $list->keyOf($e1, true));
        $this->assertSame($e1, $list->firstOf($e3));
        $this->assertNull($list->firstOf($e4));

        $first = null;
        $this->assertSame([$e1, $e2], $list->shift($first)->all());
        $this->assertSame($e0, $first);

        $last = null;
        $this->assertSame([$e0, $e1], $list->pop($last)->all());
        $this->assertSame($e2, $last);

        $l = $list->set(2, $e4);
        $this->assertSame([$e0, $e1, $e4], $l->all());
        $l = $l->unset(0);
        $this->assertSame([$e1, $e4], $l->all());
        $l = $l->merge([13 => $e0, 7 => $e2, 11 => $e4]);
        $this->assertSame([$e1, $e4, $e0, $e2, $e4], $l->all());
    }

    public function testEmptyListCollection(): void
    {
        /** @var ListCollection<MyComparableClass> */
        $list = new ListCollection();

        /** @disregard P1008 */
        $l = $list->pop($last);
        $this->assertSame($list, $l);
        /** @disregard P1008 */
        $this->assertNull($last);

        /** @disregard P1008 */
        $l = $list->shift($first);
        $this->assertSame($list, $l);
        /** @disregard P1008 */
        $this->assertNull($first);

        $l = $list->sort();
        $this->assertSame($list, $l);

        $l = $list->reverse();
        $this->assertSame($list, $l);

        $count = 0;
        $list->forEach(
            function () use (&$count) {
                $count++;
            }
        );
        $this->assertSame($l, $list);
        $this->assertSame(0, $count);

        $l = $list->map(fn(MyComparableClass $item) => $item);
        $this->assertSame($list, $l);

        $l = $list->filter(fn() => true);
        $this->assertSame($list, $l);

        $l = $list->only([]);
        $this->assertSame($list, $l);

        $l = $list->onlyIn([]);
        $this->assertSame($list, $l);

        $l = $list->except([]);
        $this->assertSame($list, $l);

        $l = $list->exceptIn([]);
        $this->assertSame($list, $l);

        $this->assertNull($list->find(fn() => true));

        $l = $list->slice(0);
        $this->assertSame($list, $l);

        $l = $list->merge([]);
        $this->assertSame($list, $l);

        $e0 = new MyComparableClass('foo');
        $this->assertFalse($list->hasValue($e0));
        $this->assertFalse($list->hasValue($e0, true));
        $this->assertNull($list->keyOf($e0));
        $this->assertNull($list->keyOf($e0, true));
        $this->assertNull($list->firstOf($e0));
        $this->assertSame([], $list->all());
        $this->assertSame([], $list->toArray());
        $this->assertNull($list->first());
        $this->assertNull($list->last());
        $this->assertNull($list->nth(1));
        $this->assertFalse($list->has(0));

        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Item not found: 0');
        $list->get(0);
    }

    public function testInvalidKeyType(): void
    {
        /** @var ListCollection<MyComparableClass> */
        $list = new ListCollection();
        // @phpstan-ignore offsetAssign.dimType
        $list['foo'] = $value = new MyComparableClass('bar');
        $this->assertSame([$value], $list->all());
    }

    public function testSetInvalidKeyType(): void
    {
        $list = new ListCollection();
        // @phpstan-ignore argument.type
        $list = $list->set('foo', $value = new MyComparableClass('bar'));
        $this->assertSame([$value], $list->all());
    }

    public function testInvalidKey(): void
    {
        /** @var ListCollection<MyComparableClass> */
        $list = new ListCollection();
        $list[1] = $value = new MyComparableClass('foo');
        $this->assertSame([$value], $list->all());
    }

    public function testSetInvalidKey(): void
    {
        $list = new ListCollection();
        $list = $list->set(1, $value = new MyComparableClass('foo'));
        $this->assertSame([$value], $list->all());
    }
}
