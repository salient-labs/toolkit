<?php declare(strict_types=1);

namespace Salient\Iterator;

use Iterator;
use OutOfRangeException;
use ReturnTypeWillChange;

/**
 * @api
 *
 * @implements Iterator<array-key,mixed>
 */
class GraphIterator implements Iterator
{
    protected object $Object;
    /** @var mixed[] */
    protected array $Array;
    protected bool $IsObject;
    /** @var array-key[] */
    protected array $Keys;

    /**
     * @api
     *
     * @param mixed[]|object $value
     */
    public function __construct(&$value)
    {
        if (is_object($value)) {
            $this->Object = $value;
            $this->IsObject = true;
        } else {
            $this->Array = &$value;
            $this->IsObject = false;
        }
    }

    /**
     * @return mixed
     * @disregard P1038
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        if (
            !isset($this->Keys)
            || ($key = current($this->Keys)) === false
        ) {
            throw new OutOfRangeException('Invalid position');
        }
        return $this->IsObject
            ? $this->Object->{$key}
            : $this->Array[$key];
    }

    /**
     * @return array-key|null
     * @disregard P1038
     */
    #[ReturnTypeWillChange]
    public function key()
    {
        return !isset($this->Keys)
            || ($key = current($this->Keys)) === false
                ? null
                : $key;
    }

    /**
     * @inheritDoc
     */
    public function next(): void
    {
        if (isset($this->Keys)) {
            next($this->Keys);
        }
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->Keys = $this->IsObject
            ? array_keys(get_object_vars($this->Object))
            : array_keys($this->Array);
        reset($this->Keys);
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return isset($this->Keys)
            && current($this->Keys) !== false;
    }
}
