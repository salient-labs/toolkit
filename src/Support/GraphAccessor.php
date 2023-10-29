<?php declare(strict_types=1);

namespace Lkrms\Support;

use ArrayAccess;
use LogicException;
use ReturnTypeWillChange;
use stdClass;

/**
 * Surfaces the properties and elements of arbitrarily nested objects and arrays
 * via a unified array interface
 *
 * @implements ArrayAccess<array-key,int|float|string|bool|object|mixed[]|static>
 */
final class GraphAccessor implements ArrayAccess
{
    /**
     * @var object|mixed[]
     */
    private $Graph;

    /**
     * @var array<array-key,static>
     */
    private $Accessors;

    /**
     * @var bool
     */
    private $IsObject = true;

    /**
     * @var static|null
     */
    private $Parent;

    /**
     * @var array-key|null
     */
    private $ParentOffset;

    /**
     * @param object|mixed[] $graph
     */
    public function __construct(&$graph)
    {
        if (is_object($graph)) {
            $this->Graph = $graph;
            return;
        }

        $this->Graph = &$graph;
        $this->IsObject = false;
    }

    public function offsetExists($offset): bool
    {
        if ($this->IsObject) {
            return property_exists($this->Graph, $offset);
        }

        return array_key_exists($offset, $this->Graph);
    }

    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        // If there is nothing at the requested offset, create a new object or
        // array, add it to the graph and return a new `GraphAccessor` for it
        if (!$this->offsetExists($offset)) {
            if ($this->IsObject) {
                $value = new stdClass();
                $this->Graph->$offset = $value;
                return $this->Accessors[$offset] =
                    (new self($value))
                        ->withParent($this, $offset);
            }
            $this->Graph[$offset] = [];
            return $this->Accessors[$offset] =
                (new self($this->Graph[$offset]))
                    ->withParent($this, $offset);
        }

        $value =
            $this->IsObject
                ? $this->Graph->$offset
                : $this->Graph[$offset];

        if (is_object($value) || is_array($value)) {
            if ($this->IsObject) {
                return $this->Accessors[$offset]
                    ?? ($this->Accessors[$offset] =
                        new self($this->Graph->$offset));
            } else {
                return $this->Accessors[$offset]
                    ?? ($this->Accessors[$offset] =
                        new self($this->Graph[$offset]));
            }
        }

        return $value;
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            // `$normaliser[] = $value` is supported if `$this->Graph` is an
            // array (or a `stdClass` that can be safely converted to an array)
            if ($this->IsObject && $this->Parent) {
                if ($this->Parent->IsObject) {
                    $this->Parent->Graph->{$this->ParentOffset} = (array) $this->Graph;
                    $this->Graph = &$this->Parent->Graph->{$this->ParentOffset};
                } else {
                    $this->Parent->Graph[$this->ParentOffset] = (array) $this->Graph;
                    $this->Graph = &$this->Parent->Graph[$this->ParentOffset];
                }
                $this->IsObject = false;
            } elseif ($this->IsObject) {
                throw new LogicException('Offset required');
            }

            $this->Graph[] = $value;
            return;
        }

        unset($this->Accessors[$offset]);

        if ($this->IsObject) {
            $this->Graph->$offset = $value;
            return;
        }

        $this->Graph[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->Accessors[$offset]);

        if ($this->IsObject) {
            unset($this->Graph->$offset);
            return;
        }

        unset($this->Graph[$offset]);
    }

    /**
     * @param static $parent
     * @param array-key $offset
     * @return $this
     */
    private function withParent($parent, $offset)
    {
        $this->Parent = $parent;
        $this->ParentOffset = $offset;

        return $this;
    }
}
