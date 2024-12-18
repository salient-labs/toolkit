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
        if ($this->IsObject) {
            if (!property_exists($this->Object, (string) $offset)) {
                if (!$this->AddMissingProperties) {
                    throw new OutOfRangeException(sprintf('Property not found: %s', $offset));
                }
                $this->Object->$offset = [];
            }
            if (!(is_object($this->Object->$offset) || is_array($this->Object->$offset))) {
                return $this->Object->$offset;
            }
            $value = new static($this->Object->$offset);
        } else {
            if (!array_key_exists($offset, $this->Array)) {
                if (!$this->AddMissingKeys) {
                    throw new OutOfRangeException(sprintf('Key not found: %s', $offset));
                }
                $this->Array[$offset] = [];
            }
            if (!(is_object($this->Array[$offset]) || is_array($this->Array[$offset]))) {
                /** @var int|float|string|bool|null */
                return $this->Array[$offset];
            }
            $value = new static($this->Array[$offset]);
        }

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
