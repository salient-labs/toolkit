<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept;

use Lkrms\Tests\Concept\TypedList\MyClass;
use Lkrms\Tests\Concept\TypedList\MyList;
use Lkrms\Tests\TestCase;
use Salient\Core\Exception\InvalidArgumentException;

final class TypedListTest extends TestCase
{
    public function testTypedList(): void
    {
        $list = new MyList();

        $e0 = new MyClass('delta');
        $e1 = new MyClass('charlie');
        $e2 = new MyClass('november');

        $list[] = $e0;
        $list[] = $e1;
        $list[] = $e2;

        $this->assertTrue(isset($list[2]));
        unset($list[2]);
        $this->assertFalse(isset($list[2]));
        $list[2] = $e2;
        $this->assertSame([0 => $e0, 1 => $e1, 2 => $e2], $list->all());
        $sorted = $list->sort();
        $sorted2 = $sorted->sort();
        $this->assertNotSame($sorted, $list);
        $this->assertSame($sorted2, $sorted);
        $this->assertSame([0 => $e2, 1 => $e1, 2 => $e0], $sorted->all());
        $reversed = $sorted->reverse();
        $this->assertNotSame($reversed, $sorted);
        $this->assertSame([0 => $e0, 1 => $e1, 2 => $e2], $reversed->all());
        $this->assertCount(3, $list);

        foreach ($list as $key => $value) {
            $arr[$key] = $value;
        }
        $this->assertSame([0 => $e0, 1 => $e1, 2 => $e2], $arr ?? null);

        $arr = $arrNext = $arrPrev = [];
        $l = $list->forEach(
            function ($item, $next, $prev) use (&$arr, &$arrNext, &$arrPrev) {
                $arr[] = $item;
                $arrNext[] = $next;
                $arrPrev[] = $prev;
            }
        );
        $this->assertSame($list, $l);
        $this->assertSame([$e0, $e1, $e2], $arr);
        $this->assertSame([$e1, $e2, null], $arrNext);
        $this->assertSame([null, $e0, $e1], $arrPrev);

        $arr = $arrNext = $arrPrev = [];
        $l = $list->filter(
            function ($item, $next, $prev) use (&$arr, &$arrNext, &$arrPrev): bool {
                $arr[] = $item;
                $arrNext[] = $next;
                $arrPrev[] = $prev;

                return (bool) $prev;
            }
        );
        $this->assertSame([0 => $e1, 1 => $e2], $l->all());
        $this->assertSame([$e0, $e1, $e2], $arr);
        $this->assertSame([$e1, $e2, null], $arrNext);
        $this->assertSame([null, $e0, $e1], $arrPrev);

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
            function ($item, $next, $prev) use (&$arr, &$arrNext, &$arrPrev): bool {
                $arr[] = $item;
                $arrNext[] = $next;
                $arrPrev[] = $prev;

                return !$next;
            }
        );
        $this->assertSame($e2, $found);
        $this->assertSame([$e0, $e1, $e2], $arr);
        $this->assertSame([$e1, $e2, null], $arrNext);
        $this->assertSame([null, $e0, $e1], $arrPrev);

        $this->assertNull($list->find(fn() => false));

        $slice = $list->slice(1, 1);
        $this->assertSame([0 => $e1], $slice->toArray());

        $e3 = new MyClass('charlie');
        $e4 = new MyClass('echo');
        $this->assertTrue($list->has($e3));
        $this->assertFalse($list->has($e3, true));
        $this->assertSame(1, $list->keyOf($e3));
        $this->assertNull($list->keyOf($e3, true));
        $this->assertSame(1, $list->keyOf($e1, true));
        $this->assertSame($e1, $list->get($e3));
        $this->assertNull($list->get($e4));

        $list->set(2, $e4);
        $this->assertSame([0 => $e0, 1 => $e1, 2 => $e4], $list->all());
        $list->unset(0);
        $this->assertSame([0 => $e1, 1 => $e4], $list->all());
        $list->merge([13 => $e0, 7 => $e2, 11 => $e4]);
        $this->assertSame([0 => $e1, 1 => $e4, 2 => $e0, 3 => $e2, 4 => $e4], $list->all());
    }

    public function testEmptyTypedList(): void
    {
        $list = new MyList();

        $l = $list->pop($last);
        $this->assertSame($list, $l);
        $this->assertNull($last);

        $l = $list->shift($first);
        $this->assertSame($list, $l);
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

        $e0 = new MyClass('foo');
        $this->assertFalse($list->has($e0));
        $this->assertFalse($list->has($e0, true));
        $this->assertNull($list->keyOf($e0));
        $this->assertNull($list->keyOf($e0, true));
        $this->assertNull($list->get($e0));
        $this->assertSame([], $list->all());
        $this->assertSame([], $list->toArray());
        $this->assertNull($list->first());
        $this->assertNull($list->last());
        $this->assertNull($list->nth(1));
    }

    public function testInvalidKeyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument #1 ($offset) must be of type int, string given');
        $list = new MyList();
        // @phpstan-ignore-next-line
        $list['foo'] = new MyClass('bar');
    }

    public function testSetInvalidKeyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument #1 ($key) must be of type int, string given');
        $list = new MyList();
        // @phpstan-ignore-next-line
        $list->set('foo', new MyClass('bar'));
    }

    public function testInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item cannot be added with key: 1');
        $list = new MyList();
        $list[1] = new MyClass('foo');
    }

    public function testSetInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item cannot be added with key: 1');
        $list = new MyList();
        $list->set(1, new MyClass('foo'));
    }
}
