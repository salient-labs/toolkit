<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Core\Graph;
use Salient\Tests\TestCase;
use OutOfRangeException;
use stdClass;

/**
 * @covers \Salient\Core\Graph
 */
final class GraphTest extends TestCase
{
    public function testWithObject(): void
    {
        $value = new stdClass();
        $graph = new Graph($value);

        $graph['foo'] = 'bar';
        /** @disregard P1006 */
        $this->assertSame('bar', $value->foo);

        $graph['q'] = new stdClass();
        $this->assertInstanceOf(Graph::class, $graph['q']);
        $graph['q']['qux'] = new stdClass();
        $this->assertInstanceOf(Graph::class, $graph['q']['qux']);
        $graph['q']['qux']['quux'] = 'foobar';
        /** @disregard P1006 */
        $this->assertSame('foobar', $value->q->qux->quux);

        unset($graph['q']['qux']);
        $this->assertFalse(isset($graph['q']['qux']['quux']));
        $this->assertFalse(isset($graph['q']['qux']));

        $graph['arr'] = ['alpha', 'bravo', 'charlie'];
        $this->assertInstanceOf(Graph::class, $graph['arr']);
        $graph['arr'][] = 'delta';
        /** @disregard P1006 */
        $this->assertTrue(is_array($value->arr));
        /** @disregard P1006 */
        $this->assertSame(['alpha', 'bravo', 'charlie', 'delta'], $value->arr);

        $graph['obj'] = new stdClass();
        $this->assertInstanceOf(Graph::class, $graph['obj']);
        $graph['obj']['a'] = 'alpha';
        $graph['obj']['b'] = 'bravo';
        $graph['obj'][0] = 'charlie';
        $graph['obj'][1] = 'delta';
        /** @disregard P1006 */
        $this->assertFalse(is_array($value->obj));
        /** @disregard P1006 */
        $this->assertSame(['a' => 'alpha', 'b' => 'bravo', 'charlie', 'delta'], (array) $value->obj);

        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Offset not found: bar');
        // @phpstan-ignore offsetAccess.nonOffsetAccessible
        $graph['bar'][] = 'foo';
    }

    public function testWithArray(): void
    {
        $value = [];
        $graph = new Graph($value);

        $graph['foo'] = 'bar';
        $this->assertSame('bar', $value['foo']);

        $graph['q'] = [];
        $this->assertInstanceOf(Graph::class, $graph['q']);
        $graph['q']['qux'] = [];
        $this->assertInstanceOf(Graph::class, $graph['q']['qux']);
        $graph['q']['qux']['quux'] = 'foobar';
        $this->assertSame('foobar', $value['q']['qux']['quux']);

        unset($graph['q']['qux']);
        $this->assertFalse(isset($graph['q']['qux']['quux']));
        $this->assertFalse(isset($graph['q']['qux']));

        $graph['arr'] = ['alpha', 'bravo', 'charlie'];
        $this->assertInstanceOf(Graph::class, $graph['arr']);
        $graph['arr'][] = 'delta';
        $this->assertFalse(is_array($graph['arr']));
        $this->assertTrue(is_array($value['arr']));
        $this->assertSame(['alpha', 'bravo', 'charlie', 'delta'], $value['arr']);

        $graph['obj'] = [];
        $this->assertInstanceOf(Graph::class, $graph['obj']);
        $graph['obj']['a'] = 'alpha';
        $graph['obj']['b'] = 'bravo';
        $graph['obj'][0] = 'charlie';
        $graph['obj'][1] = 'delta';
        $this->assertTrue(is_array($value['obj']));
        $this->assertSame(['a' => 'alpha', 'b' => 'bravo', 'charlie', 'delta'], $value['obj']);

        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Offset not found: bar');
        // @phpstan-ignore offsetAccess.nonOffsetAccessible
        $graph['bar'][] = 'foo';
    }

    public function testOffsetSet(): void
    {
        $value = new stdClass();
        $graph = new Graph($value);

        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Invalid offset');
        $graph[] = 'foo';
    }
}
