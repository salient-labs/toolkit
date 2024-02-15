<?php declare(strict_types=1);

namespace Lkrms\Tests\Support;

use Lkrms\Support\Graph;
use Lkrms\Tests\TestCase;
use LogicException;
use stdClass;

final class GraphTest extends TestCase
{
    public function testWithObject(): void
    {
        $graph = new stdClass();
        $normaliser = new Graph($graph, stdClass::class);

        $normaliser['foo'] = 'bar';
        $this->assertSame('bar', $graph->foo);

        $normaliser['q']['qux']['quux'] = 'foobar';
        $this->assertSame('foobar', $graph->q->qux->quux);

        unset($normaliser['q']['qux']);
        $this->assertFalse(isset($normaliser['q']['qux']['quux']));
        $this->assertFalse(isset($normaliser['q']['qux']));

        $normaliser['arr'] = ['alpha', 'bravo', 'charlie'];
        $normaliser['arr'][3] = 'delta';
        $this->assertFalse(is_array($normaliser['arr']));
        $this->assertTrue(is_array($graph->arr));
        $this->assertSame(['alpha', 'bravo', 'charlie', 'delta'], $graph->arr);

        $normaliser['obj']['a'] = 'alpha';
        $normaliser['obj']['b'] = 'bravo';
        $this->assertFalse(is_array($normaliser['obj']));
        $this->assertFalse(is_array($graph->obj));
        $this->assertSame(['a' => 'alpha', 'b' => 'bravo'], (array) $graph->obj);

        $normaliser['obj'][0] = 'charlie';
        $normaliser['obj'][1] = 'delta';
        $this->assertFalse(is_array($normaliser['obj']));
        $this->assertFalse(is_array($graph->obj));
        $this->assertSame(['a' => 'alpha', 'b' => 'bravo', 'charlie', 'delta'], (array) $graph->obj);

        $this->expectException(LogicException::class);
        $normaliser['obj'][] = 'echo';
    }

    public function testWithArray(): void
    {
        $graph = [];
        $normaliser = new Graph($graph);

        $normaliser['foo'] = 'bar';
        $this->assertSame('bar', $graph['foo']);

        $normaliser['q']['qux']['quux'] = 'foobar';
        $this->assertSame('foobar', $graph['q']['qux']['quux']);

        unset($normaliser['q']['qux']);
        $this->assertFalse(isset($normaliser['q']['qux']['quux']));
        $this->assertFalse(isset($normaliser['q']['qux']));

        $normaliser['arr'] = ['alpha', 'bravo', 'charlie'];
        $normaliser['arr'][] = 'delta';
        $this->assertFalse(is_array($normaliser['arr']));
        $this->assertTrue(is_array($graph['arr']));
        $this->assertSame(['alpha', 'bravo', 'charlie', 'delta'], $graph['arr']);

        $normaliser['obj']['a'] = 'alpha';
        $normaliser['obj']['b'] = 'bravo';
        $this->assertFalse(is_array($normaliser['obj']));
        $this->assertTrue(is_array($graph['obj']));
        $this->assertSame(['a' => 'alpha', 'b' => 'bravo'], $graph['obj']);

        $normaliser['obj'][] = 'charlie';
        $normaliser['obj'][] = 'delta';
        $this->assertFalse(is_array($normaliser['obj']));
        $this->assertTrue(is_array($graph['obj']));
        $this->assertSame(['a' => 'alpha', 'b' => 'bravo', 'charlie', 'delta'], $graph['obj']);
    }
}
