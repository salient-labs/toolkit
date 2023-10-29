<?php declare(strict_types=1);

namespace Lkrms\Tests\Support;

use Lkrms\Support\GraphNormaliser;
use Lkrms\Tests\TestCase;
use stdClass;

final class GraphNormaliserTest extends TestCase
{
    public function testArrayAccess(): void
    {
        $graph = new stdClass();
        $normaliser = new GraphNormaliser($graph);

        $normaliser['foo'] = 'bar';
        $this->assertSame('bar', $graph->foo);

        $normaliser['q']['qux']['quux'] = 'foobar';
        $this->assertSame('foobar', $graph->q->qux->quux);

        unset($normaliser['q']['qux']);
        $this->assertSame(false, isset($normaliser['q']['qux']['quux']));
        $this->assertSame(false, isset($normaliser['q']['qux']));

        $normaliser['arr'] = ['alpha', 'bravo', 'charlie'];
        $normaliser['arr'][] = 'delta';
        $this->assertSame(false, is_array($normaliser['arr']));
        $this->assertSame(true, is_array($graph->arr));
        $this->assertSame(['alpha', 'bravo', 'charlie', 'delta'], $graph->arr);

        $normaliser['obj']['a'] = 'alpha';
        $normaliser['obj']['b'] = 'bravo';
        $this->assertSame(false, is_array($normaliser['obj']));
        $this->assertSame(false, is_array($graph->obj));
        $this->assertSame(['a' => 'alpha', 'b' => 'bravo'], (array) $graph->obj);

        $normaliser['obj'][] = 'charlie';
        $normaliser['obj'][] = 'delta';
        $this->assertSame(false, is_array($normaliser['obj']));
        $this->assertSame(true, is_array($graph->obj));
        $this->assertSame(['a' => 'alpha', 'b' => 'bravo', 'charlie', 'delta'], (array) $graph->obj);
    }
}
