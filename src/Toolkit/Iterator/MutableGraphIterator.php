<?php declare(strict_types=1);

namespace Salient\Iterator;

use Salient\Iterator\Contract\MutableIterator;
use LogicException;

/**
 * Iterates over the properties of an object or the elements of an array while
 * allowing the current element to be replaced
 *
 * @api
 *
 * @implements MutableIterator<array-key,mixed>
 */
class MutableGraphIterator extends GraphIterator implements MutableIterator
{
    /**
     * @param object|mixed[] $graph
     */
    public function __construct(&$graph)
    {
        $this->doConstruct($graph);
    }

    /**
     * @inheritDoc
     */
    public function replace($value)
    {
        $key = current($this->Keys);
        if ($key === false) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Current position is not valid');
            // @codeCoverageIgnoreEnd
        }

        if ($this->IsObject) {
            $this->Graph->{$key} = $value;
            return $this;
        }

        // @phpstan-ignore offsetAccess.nonOffsetAccessible
        $this->Graph[$key] = $value;
        return $this;
    }
}
