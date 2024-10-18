<?php declare(strict_types=1);

namespace Salient\Iterator\Concern;

use Salient\Iterator\GraphIterator;
use RecursiveIterator;

/**
 * Implements RecursiveIterator for GraphIterator subclasses
 *
 * @api
 *
 * @phpstan-require-extends GraphIterator
 * @phpstan-require-implements RecursiveIterator
 */
trait RecursiveGraphIteratorTrait
{
    /**
     * @inheritDoc
     */
    public function hasChildren(): bool
    {
        /** @var GraphIterator $this */
        $current = $this->current();
        if ($current === false) {
            // @codeCoverageIgnoreStart
            return false;
            // @codeCoverageIgnoreEnd
        }

        return is_object($current) || is_array($current);
    }

    /**
     * @inheritDoc
     */
    public function getChildren(): ?self
    {
        /** @var GraphIterator $this */
        $key = $this->key();
        if ($key === null) {
            // @codeCoverageIgnoreStart
            return null;
            // @codeCoverageIgnoreEnd
        }

        if ($this->IsObject) {
            $current = &$this->Graph->{$key};
        } else {
            // @phpstan-ignore-next-line
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
