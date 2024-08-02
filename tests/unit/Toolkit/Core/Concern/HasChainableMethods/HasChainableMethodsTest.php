<?php declare(strict_types=1);

namespace Salient\Tests\Core\Concern\HasChainableMethods;

use Salient\Contract\Core\Chainable;
use Salient\Core\Concern\HasChainableMethods;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Core\Concern\HasChainableMethods
 */
final class HasChainableMethodsTest extends TestCase
{
    public function testApply(): void
    {
        $a = new MyChainable();
        $b = $a->apply(fn(MyChainable $chainable) => $chainable);
        $c = $b->apply(fn(MyChainable $chainable) => $chainable->next());

        $this->assertSame($a, $b);
        $this->assertNotSame($b, $c);
    }

    public function testIf(): void
    {
        $callback1 = fn(MyChainable $chainable) => $chainable->next();
        $callback2 = fn(MyChainable $chainable) => $chainable->next()->next();

        $a = new MyChainable();
        $b = $a->if(true, null, $callback2);
        $c = $b->if(false, $callback1, null);
        $d = $c->if(true, $callback1, $callback2);
        $e = $d->if(false, $callback1, $callback2);
        $f = $e->if(fn() => true, null, $callback2);
        $g = $f->if(fn() => false, $callback1, null);
        $h = $g->if(fn() => true, $callback1, $callback2);
        $i = $h->if(fn() => false, $callback1, $callback2);

        $this->assertSame($a, $b);
        $this->assertSame($a, $c);
        $this->assertNotSame($c, $d);
        $this->assertSame(1, $d->id());
        $this->assertNotSame($d, $e);
        $this->assertSame(3, $e->id());
        $this->assertSame($e, $f);
        $this->assertSame($e, $g);
        $this->assertNotSame($g, $h);
        $this->assertSame(4, $h->id());
        $this->assertNotSame($h, $i);
        $this->assertSame(6, $i->id());
    }

    public function testWithEach(): void
    {
        $callback = fn(MyChainable $chainable, $value, $key) =>
            $chainable->next()->record([$key, $value]);

        $a = new MyChainable();
        $b = $a->withEach([], $callback);
        $c = $b->withEach([1, 'foo' => 2, 'BAR' => 3], $callback);

        $this->assertSame($a, $b);
        $this->assertNotSame($b, $c);
        $this->assertSame(3, $c->id());
        $this->assertSame([[0, 1], ['foo', 2], ['BAR', 3]], $c->entries());
    }
}

class MyChainable implements Chainable
{
    use HasChainableMethods;

    private int $Id = 0;
    /** @var mixed[] */
    private array $Entries = [];

    public function id(): int
    {
        return $this->Id;
    }

    /**
     * @return mixed[]
     */
    public function entries(): array
    {
        return $this->Entries;
    }

    /**
     * @param mixed $entry
     * @return $this
     */
    public function record($entry)
    {
        $this->Entries[] = $entry;
        return $this;
    }

    /**
     * @return static
     */
    public function next()
    {
        $instance = clone $this;
        $instance->Id++;
        return $instance;
    }
}
