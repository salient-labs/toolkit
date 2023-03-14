<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept;

use Lkrms\Tests\Concept\HasMutator\MyMutatingClass;

final class HasMutatorTest extends \Lkrms\Tests\TestCase
{
    public function testWithPropertyValue()
    {
        $a = new MyMutatingClass();
        $b = $a->with('A', 1);
        $c = $b->with('B', 2)
               ->with('C', $b->C * 10);
        $d = $c->with('Arr1', 'bbb', 'b')
               ->with('Arr1', 'ccc', 'c')
               ->with('Arr2', 'ddd', 'd')
               ->with('Arr3', 'eee', 'e')
               ->with('Arr4', 'fff', 'f');
        $e = $d->with('Obj', 'aa', 'A')
               ->with('Obj', 'bb', 'B');
        $f = $e->with('Obj', 'aa', 'A')
               ->with('Obj', 'bb', 'B')
               ->with('A', 1);

        $this->assertNotSame($a, $b);
        $this->assertNotEquals($a, $b);
        $this->assertNotSame($b, $c);
        $this->assertNotEquals($b, $c);
        $this->assertNotSame($c, $d);
        $this->assertNotEquals($c, $d);
        $this->assertNotSame($d, $e);
        $this->assertNotEquals($d, $e);

        $this->assertSame($e, $f);

        $this->assertNotSame($d->Obj, $e->Obj);

        $A       = new MyMutatingClass();
        $A->A    = 1;
        $A->B    = 2;
        $A->C    = 30;
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
        $this->assertEquals($A, $f);
    }
}
