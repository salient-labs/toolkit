<?php declare(strict_types=1);

namespace Salient\Iterator;

use Salient\Core\Exception\LogicException;
use ArrayAccess;
use ReturnTypeWillChange;

/**
 * Surfaces the properties and elements of arbitrarily nested objects and arrays
 * via a unified array interface
 *
 * Useful when working with data that has the same structure whether it's given
 * as an object or as an array.
 *
 * @implements ArrayAccess<array-key,int|float|string|bool|object|mixed[]|static>
 */
final class Graph implements ArrayAccess
{
    /** @var object|mixed[] */
    private $Graph;
    /** @var class-string|null */
    private $DefaultClass;
    /** @var mixed[] */
    private $Args;
    /** @var bool */
    private $IsObject = true;

    /**
     * Creates a new Graph object
     *
     * @param object|mixed[]|null $graph
     * @param class-string|null $defaultClass If `null`, arrays are added to the
     * graph as needed to accommodate values assigned by key.
     * @param mixed ...$args Passed to the constructor of `$defaultClass`.
     */
    public function __construct(
        &$graph = null,
        ?string $defaultClass = null,
        ...$args
    ) {
        $this->DefaultClass = $defaultClass;
        $this->Args = $args;

        if ($graph === null) {
            $graph = $this->createInner();
        }

        if (is_object($graph)) {
            $this->Graph = $graph;
            return;
        }

        if (!is_array($graph)) {
            throw new LogicException(
                '$graph must be an object, an array, or null'
            );
        }

        $this->Graph = &$graph;
        $this->IsObject = false;
    }

    /**
     * Creates a new Graph object backed by a given object or array
     *
     * Syntactic sugar for `new Graph()`. Returns a {@see Graph} instance that
     * operates on `$graph`, which is passed by reference.
     *
     * @param object|mixed[]|null $graph
     * @param class-string|null $defaultClass If `null`, arrays are added to the
     * graph as needed to accommodate values assigned by key.
     * @param mixed ...$args Passed to the constructor of `$defaultClass`.
     */
    public static function with(
        &$graph,
        ?string $defaultClass = null,
        ...$args
    ): self {
        return new self($graph, $defaultClass, ...$args);
    }

    /**
     * Creates a new Graph object from an object or array
     *
     * Unlike {@see Graph::with()}, {@see Graph::from()} returns a {@see Graph}
     * instance that operates on a copy of `$graph` (if it is an array).
     *
     * @param object|mixed[] $graph
     * @param class-string|null $defaultClass If `null`, arrays are added to the
     * graph as needed to accommodate values assigned by key.
     * @param mixed ...$args Passed to the constructor of `$defaultClass`.
     */
    public static function from(
        $graph,
        ?string $defaultClass = null,
        ...$args
    ): self {
        return new self($graph, $defaultClass, ...$args);
    }

    /**
     * Get the graph's underlying object or array
     *
     * @return object|mixed[]
     */
    public function inner()
    {
        return $this->Graph;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        if ($this->IsObject) {
            return property_exists($this->Graph, $offset);
        }

        return array_key_exists($offset, $this->Graph);
    }

    /**
     * @inheritDoc
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        // If there is nothing at the requested offset, create a new object or
        // array, add it to the graph and return a new `Graph` for it
        if (!$this->offsetExists($offset)) {
            $value = $this->createInner();

            if ($this->IsObject) {
                $this->Graph->$offset = $value;
            } else {
                $this->Graph[$offset] = $value;
            }
        } else {
            $value =
                $this->IsObject
                    ? $this->Graph->$offset
                    : $this->Graph[$offset];

            if (!is_object($value) && !is_array($value)) {
                return $value;
            }
        }

        if ($this->IsObject) {
            return new self(
                $this->Graph->$offset,
                $this->DefaultClass,
                ...$this->Args,
            );
        }

        return new self(
            $this->Graph[$offset],
            $this->DefaultClass,
            ...$this->Args,
        );
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            if ($this->IsObject) {
                throw new LogicException('Offset required');
            }
            $this->Graph[] = $value;
            return;
        }

        if ($this->IsObject) {
            $this->Graph->$offset = $value;
            return;
        }

        $this->Graph[$offset] = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset): void
    {
        if ($this->IsObject) {
            unset($this->Graph->$offset);
            return;
        }

        unset($this->Graph[$offset]);
    }

    /**
     * @return object|mixed[]
     */
    private function createInner()
    {
        if ($this->DefaultClass === null) {
            return [];
        }

        return new $this->DefaultClass(...$this->Args);
    }
}
