<?php declare(strict_types=1);

namespace Lkrms\Support\Iterator;

use Lkrms\Support\Iterator\Contract\MutableIterator;
use Iterator;
use ReturnTypeWillChange;
use RuntimeException;

/**
 * Iterates over an object's properties or an array's elements
 *
 * @implements Iterator<array-key,mixed>
 * @implements MutableIterator<array-key,mixed>
 */
class ObjectOrArrayIterator implements Iterator, MutableIterator
{
    /**
     * @var object|mixed[]
     */
    protected $ObjectOrArray;

    /**
     * @var array<array-key>
     */
    protected array $Keys = [];

    protected bool $IsObject = true;

    /**
     * @param object|mixed[] $objectOrArray
     */
    public function __construct(&$objectOrArray)
    {
        if (is_array($objectOrArray)) {
            $this->ObjectOrArray = &$objectOrArray;
            $this->Keys = array_keys($objectOrArray);
            $this->IsObject = false;

            return;
        }

        $this->ObjectOrArray = $objectOrArray;
        foreach ($objectOrArray as $key => $value) {
            $this->Keys[] = $key;
        }
    }

    /**
     * @return mixed|false
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        if (($key = current($this->Keys)) === false) {
            return false;
        }

        return $this->IsObject
            ? $this->ObjectOrArray->{$key}
            : $this->ObjectOrArray[$key];
    }

    public function replace($value)
    {
        if (($key = current($this->Keys)) === false) {
            throw new RuntimeException('Current position is not valid');
        }

        if ($this->IsObject) {
            $this->ObjectOrArray->{$key} = $value;

            return $this;
        }

        $this->ObjectOrArray[$key] = $value;

        return $this;
    }

    /**
     * @return array-key|null
     */
    #[ReturnTypeWillChange]
    public function key()
    {
        if (($key = current($this->Keys)) === false) {
            return null;
        }

        return $key;
    }

    public function next(): void
    {
        next($this->Keys);
    }

    public function rewind(): void
    {
        reset($this->Keys);
    }

    public function valid(): bool
    {
        return current($this->Keys) !== false;
    }
}
