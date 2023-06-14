<?php declare(strict_types=1);

namespace Lkrms\Support\Iterator;

/**
 * Iterates over object properties and array elements, descending into them
 * recursively
 *
 * @implements \RecursiveIterator<array-key,mixed>
 */
class RecursiveObjectOrArrayIterator extends ObjectOrArrayIterator implements \RecursiveIterator
{
    public function hasChildren(): bool
    {
        if (($current = $this->current()) === false) {
            return false;
        }

        return is_object($current) || is_array($current);
    }

    public function getChildren(): ?self
    {
        if (($current = $this->current()) === false) {
            return null;
        }

        $key = current($this->Keys);
        if ($this->IsObject) {
            $current = &$this->ObjectOrArray->{$key};
        } else {
            $current = &$this->ObjectOrArray[$key];
        }

        if (is_object($current) || is_array($current)) {
            return new self($current);
        }

        return null;
    }

    /**
     * If the current element is an object with children, replace it with an
     * array of its properties
     *
     * @return mixed|false `false` if the current position is invalid, otherwise
     * the current value.
     */
    public function maybeReplaceCurrentWithArray()
    {
        if (($current = $this->current()) === false) {
            return false;
        }

        if (is_object($current) && $this->hasChildren()) {
            $array = [];
            foreach ($current as $key => $value) {
                $array[$key] = $value;
            }

            return $this->replace($array);
        }

        return $current;
    }
}
