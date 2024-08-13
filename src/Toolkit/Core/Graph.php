<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\GraphInterface;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use OutOfRangeException;
use ReturnTypeWillChange;

/**
 * @api
 */
class Graph implements GraphInterface
{
    protected object $Object;
    /** @var mixed[] */
    protected array $Array;
    protected bool $IsObject;
    protected bool $AddMissingProperties;
    protected bool $AddMissingKeys;
    /** @var string[] */
    protected array $Path = [];

    /**
     * Creates a new Graph object
     */
    public function __construct(
        &$value = [],
        bool $addMissingKeys = false,
        bool $addMissingProperties = false
    ) {
        if (is_object($value)) {
            $this->Object = $value;
            $this->IsObject = true;
        } elseif (is_array($value)) {
            $this->Array = &$value;
            $this->IsObject = false;
        } else {
            throw new InvalidArgumentTypeException(1, 'value', 'mixed[]|object', $value);
        }
        $this->AddMissingProperties = $addMissingProperties;
        $this->AddMissingKeys = $addMissingKeys;
    }

    /**
     * @inheritDoc
     */
    public function getValue()
    {
        return $this->IsObject ? $this->Object : $this->Array;
    }

    /**
     * @inheritDoc
     */
    public function getPath(): array
    {
        return $this->Path;
    }

    /**
     * @param array-key $offset
     */
    public function offsetExists($offset): bool
    {
        return $this->IsObject
            ? property_exists($this->Object, (string) $offset)
            : array_key_exists($offset, $this->Array);
    }

    /**
     * @param array-key $offset
     * @disregard P1038
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            if (
                ($this->IsObject && !$this->AddMissingProperties)
                || (!$this->IsObject && !$this->AddMissingKeys)
            ) {
                throw new OutOfRangeException(sprintf('Offset not found: %s', $offset));
            }
            if ($this->IsObject) {
                $this->Object->$offset = [];
            } else {
                $this->Array[$offset] = [];
            }
        }

        $value = $this->IsObject
            ? $this->Object->$offset
            : $this->Array[$offset];

        if (!is_object($value) && !is_array($value)) {
            return $value;
        }

        $value = $this->IsObject
            ? new static($this->Object->$offset)
            : new static($this->Array[$offset]);

        $value->AddMissingProperties = $this->AddMissingProperties;
        $value->AddMissingKeys = $this->AddMissingKeys;
        $value->Path = $this->Path;
        $value->Path[] = $offset;
        return $value;
    }

    /**
     * @param array-key|null $offset
     */
    public function offsetSet($offset, $value): void
    {
        if ($this->IsObject) {
            if ($offset === null) {
                throw new OutOfRangeException('Invalid offset');
            }
            $this->Object->$offset = $value;
        } elseif ($offset === null) {
            $this->Array[] = $value;
        } else {
            $this->Array[$offset] = $value;
        }
    }

    /**
     * @param array-key $offset
     */
    public function offsetUnset($offset): void
    {
        if ($this->IsObject) {
            unset($this->Object->$offset);
        } else {
            unset($this->Array[$offset]);
        }
    }
}
