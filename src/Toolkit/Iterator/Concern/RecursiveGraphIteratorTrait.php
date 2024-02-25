<?php declare(strict_types=1);

namespace Lkrms\Iterator\Concern;

use Lkrms\Iterator\RecursiveGraphIterator;
use Lkrms\Iterator\RecursiveMutableGraphIterator;

/**
 * Implements RecursiveIterator for RecursiveGraphIterator and
 * RecursiveMutableGraphIterator
 */
trait RecursiveGraphIteratorTrait
{
    public function hasChildren(): bool
    {
        /** @var RecursiveGraphIterator|RecursiveMutableGraphIterator $this */
        if (($current = $this->current()) === false) {
            // @codeCoverageIgnoreStart
            return false;
            // @codeCoverageIgnoreEnd
        }

        return is_object($current) || is_array($current);
    }

    public function getChildren(): ?self
    {
        /** @var RecursiveGraphIterator|RecursiveMutableGraphIterator $this */
        if (($current = $this->current()) === false) {
            // @codeCoverageIgnoreStart
            return null;
            // @codeCoverageIgnoreEnd
        }

        $key = current($this->Keys);
        if ($this->IsObject) {
            $current = &$this->Graph->{$key};
        } else {
            $current = &$this->Graph[$key];
        }

        if (is_object($current) || is_array($current)) {
            return new self($current);
        }

        // @codeCoverageIgnoreStart
        return null;
        // @codeCoverageIgnoreEnd
    }
}
