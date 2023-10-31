<?php declare(strict_types=1);

namespace Lkrms\Tests\Concern;

use Lkrms\Tests\Concern\HasMutator\MyMutatingClass;
use LogicException;

final class HasMutatorTest extends \Lkrms\Tests\TestCase
{
    public function testWithPropertyValue(): void
    {
        $a = new MyMutatingClass();
        $b = $a->with('A', 1);
        $c = $b
            ->with('B', 2)
            ->with('C', $b->C * 10);
        $d = $c
            ->with('Arr1', 'bbb', 'b')
            ->with('Arr1', 'ccc', 'c')
            ->with('Arr2', 'ddd', 'd')
            ->with('Arr3', 'eee', 'e')
            ->with('Arr4', 'fff', 'f');
        $e = $d
            ->with('Obj', 'aa', 'A')
            ->with('Obj', 'bb', 'B');
        $f = $e
            ->with('A', 1)  // Changes to $f should be no-ops
            ->with('Obj', 'aa', 'A')
            ->with('Obj', 'bb', 'B');
        $g = $f->with('Coll', new \stdClass(), 'g');
        $h = $g->asNew();

        $this->assertNotSame($a, $b);
        $this->assertNotEquals($a, $b);
        $this->assertNotSame($b, $c);
        $this->assertNotEquals($b, $c);
        $this->assertNotSame($c, $d);
        $this->assertNotEquals($c, $d);
        $this->assertNotSame($d, $e);
        $this->assertNotEquals($d, $e);
        $this->assertNotSame($f, $g);
        $this->assertNotEquals($f, $g);
        $this->assertNotSame($g, $h);
        $this->assertNotEquals($g, $h);

        $this->assertSame($e, $f);

        $this->assertSame($c->Obj, $d->Obj);
        $this->assertNotSame($d->Obj, $e->Obj);

        $this->assertSame($d->Coll, $e->Coll);
        $this->assertNotSame($f->Coll, $g->Coll);

        $A = new MyMutatingClass();
        $A->A = 1;
        $A->B = 2;
        $A->C = 30;
        $A->Arr1 = [
            'a' => 'foo',
            'b' => 'bbb',
            'c' => 'ccc',
        ];
        $A->Arr2 = [
            'd' => 'ddd',
        ];
        $A->Arr3 = [
            'e' => 'eee',
        ];
        $A->Arr4 = [
            'f' => 'fff',
        ];
        $A->Obj->A = 'aa';
        $A->Obj->B = 'bb';
        $A->Coll['g'] = new \stdClass();

        $this->assertEquals($A, $h);

        $this->expectException(LogicException::class);
        $g->with('B', 5, 'index');
    }
}
