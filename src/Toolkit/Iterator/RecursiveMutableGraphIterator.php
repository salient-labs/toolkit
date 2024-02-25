<?php declare(strict_types=1);

namespace Salient\Iterator;

use Salient\Iterator\Concern\RecursiveGraphIteratorTrait;
use RecursiveIterator;

/**
 * Iterates over the properties and elements of mutable objects and arrays,
 * descending into them recursively
 *
 * @implements RecursiveIterator<array-key,mixed>
 */
class RecursiveMutableGraphIterator extends MutableGraphIterator implements RecursiveIterator
{
    use RecursiveGraphIteratorTrait;

    /**
     * If the current element is an object with children, replace it with an
     * array of its properties
     *
     * @return $this
     */
    public function maybeConvertToArray()
    {
        $current = $this->current();
        if ($current === false) {
            // @codeCoverageIgnoreStart
            return $this;
            // @codeCoverageIgnoreEnd
        }

        if (is_object($current) && $this->hasChildren()) {
            $array = [];
            foreach ($current as $key => $value) {
                $array[$key] = $value;
            }

            return $this->replace($array);
        }

        return $this;
    }
}
