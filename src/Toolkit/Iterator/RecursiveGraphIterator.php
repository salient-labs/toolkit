<?php declare(strict_types=1);

namespace Salient\Iterator;

use RecursiveIterator;

/**
 * Iterates over nested arrays and objects
 *
 * @api
 *
 * @implements RecursiveIterator<array-key,mixed>
 */
class RecursiveGraphIterator extends GraphIterator implements RecursiveIterator
{
    /**
     * @api
     *
     * @param mixed[]|object $value
     */
    final public function __construct(&$value)
    {
        parent::__construct($value);
    }

    /**
     * @inheritDoc
     */
    public function hasChildren(): bool
    {
        return is_object($current = $this->current()) || is_array($current);
    }

    /**
     * @inheritDoc
     */
    public function getChildren(): ?self
    {
        if (($key = $this->key()) !== null) {
            if ($this->IsObject) {
                $current = &$this->Object->{$key};
            } else {
                $current = &$this->Array[$key];
            }
            if (is_object($current) || is_array($current)) {
                return new static($current);
            }
        }
        return null;
    }
}
