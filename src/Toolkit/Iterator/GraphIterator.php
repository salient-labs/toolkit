<?php declare(strict_types=1);

namespace Salient\Iterator;

use Salient\Core\Exception\LogicException;
use Iterator;
use ReturnTypeWillChange;
use Traversable;

/**
 * Iterates over the properties of an object or the elements of an array
 *
 * @api
 *
 * @implements Iterator<array-key,mixed>
 */
class GraphIterator implements Iterator
{
    /** @var object|mixed[] */
    protected $Graph;
    /** @var array<array-key> */
    protected array $Keys = [];
    protected bool $IsObject = true;

    /**
     * Creates a new GraphIterator object
     *
     * @param object|mixed[] $graph
     */
    public function __construct($graph)
    {
        $this->doConstruct($graph);
    }

    /**
     * @param object|mixed[] $graph
     */
    protected function doConstruct(&$graph): void
    {
        if (is_array($graph)) {
            $this->Graph = &$graph;
            $this->Keys = array_keys($graph);
            $this->IsObject = false;
            return;
        }

        if ($graph instanceof Traversable) {
            throw new LogicException('Traversable objects are not supported');
        }

        $this->Graph = $graph;
        // @phpstan-ignore-next-line
        foreach ($graph as $key => $value) {
            $this->Keys[] = $key;
        }
    }

    /**
     * @return mixed|false
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        $key = current($this->Keys);
        if ($key === false) {
            // @codeCoverageIgnoreStart
            return false;
            // @codeCoverageIgnoreEnd
        }

        return $this->IsObject
            ? $this->Graph->{$key}
            // @phpstan-ignore-next-line
            : $this->Graph[$key];
    }

    /**
     * @return array-key|null
     */
    #[ReturnTypeWillChange]
    public function key()
    {
        $key = current($this->Keys);
        if ($key === false) {
            // @codeCoverageIgnoreStart
            return null;
            // @codeCoverageIgnoreEnd
        }
        return $key;
    }

    /**
     * @inheritDoc
     */
    public function next(): void
    {
        next($this->Keys);
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        reset($this->Keys);
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return current($this->Keys) !== false;
    }
}
