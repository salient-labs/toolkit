<?php declare(strict_types=1);

namespace Lkrms\Iterator;

use Lkrms\Iterator\Contract\MutableIterator;
use LogicException;

/**
 * Iterates over the properties or elements of a mutable object or array
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

        $this->Graph[$key] = $value;
        return $this;
    }
}
