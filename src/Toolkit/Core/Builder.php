<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\BuilderInterface;
use Salient\Core\Concern\ChainableTrait;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Core\Exception\InvalidDataException;
use Salient\Core\Reflection\ClassReflection;
use Salient\Core\Reflection\MethodReflection;
use Salient\Core\Reflection\ParameterIndex;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Salient\Utility\Str;
use Closure;

/**
 * @api
 *
 * @template TClass of object
 *
 * @implements BuilderInterface<TClass>
 */
abstract class Builder implements BuilderInterface
{
    use ChainableTrait;
    use ImmutableTrait;

    /**
     * Get the class to instantiate
     *
     * @return class-string<TClass>
     */
    abstract protected static function getService(): string;

    /**
     * Get a static method to call on the service class to create an instance,
     * or null to use the constructor
     */
    protected static function getStaticConstructor(): ?string
    {
        return null;
    }

    /**
     * Get methods to forward to a new instance of the service class
     *
     * @return string[]
     */
    protected static function getTerminators(): array
    {
        return [];
    }

    /** @var class-string<TClass> */
    private string $Service;
    private ?string $StaticConstructor;
    /** @var Closure(string, bool=): string */
    private Closure $Normaliser;
    /** @var array<string,true> */
    private array $Terminators = [];
    private ?ParameterIndex $ParameterIndex = null;
    /** @var array<string,mixed> */
    private array $Data = [];

    /**
     * @api
     */
    final public function __construct()
    {
        $this->Service = static::getService();
        $this->StaticConstructor = static::getStaticConstructor();

        $class = new ClassReflection($this->Service);

        $this->Normaliser = $class->getNormaliser()
            ?? fn(string $name) => Str::camel($name);

        foreach (static::getTerminators() as $terminator) {
            $this->Terminators[$terminator] = true;
            $this->Terminators[($this->Normaliser)($terminator, false)] = true;
        }

        /** @var MethodReflection|null $constructor */
        $constructor = $this->StaticConstructor === null
            ? $class->getConstructor()
            : $class->getMethod($this->StaticConstructor);

        if ($constructor) {
            $this->ParameterIndex = $constructor->getParameterIndex($this->Normaliser);
        }
    }

    /**
     * @inheritDoc
     */
    final public static function create()
    {
        return new static();
    }

    /**
     * @inheritDoc
     */
    final public static function resolve($object)
    {
        if ($object instanceof static) {
            $object = $object->build();
        } elseif (!is_a($object, static::getService())) {
            throw new InvalidArgumentTypeException(
                1,
                'object',
                static::class . '|' . static::getService(),
                $object,
            );
        }
        return $object;
    }

    /**
     * Get a value applied to the builder
     *
     * @return mixed
     */
    final public function getB(string $name)
    {
        return $this->Data[($this->Normaliser)($name)] ?? null;
    }

    /**
     * Check if a value has been applied to the builder
     */
    final public function issetB(string $name): bool
    {
        return array_key_exists(($this->Normaliser)($name), $this->Data);
    }

    /**
     * Remove a value applied to the builder
     *
     * @return static
     */
    final public function unsetB(string $name)
    {
        $data = $this->Data;
        unset($data[($this->Normaliser)($name)]);
        return $this->with('Data', $data);
    }

    /**
     * @inheritDoc
     */
    final public function build()
    {
        $data = $this->Data;
        if ($this->ParameterIndex) {
            $args = $this->ParameterIndex->DefaultArguments;
            $argCount = $this->ParameterIndex->RequiredArgumentCount;
            foreach ($data as $name => $value) {
                $_name = $this->ParameterIndex->Names[$name] ?? null;
                if ($_name !== null) {
                    $pos = $this->ParameterIndex->Positions[$_name];
                    $argCount = max($argCount, $pos + 1);
                    if (isset($this->ParameterIndex->PassedByReference[$name])) {
                        $args[$pos] = &$data[$name];
                    } else {
                        $args[$pos] = $value;
                    }
                    unset($data[$name]);
                }
            }
            if (count($args) > $argCount) {
                $args = array_slice($args, 0, $argCount);
            }
        }
        if ($data) {
            throw new InvalidDataException(sprintf(
                'Cannot call %s::%s() with: %s',
                $this->Service,
                $this->StaticConstructor ?? '__construct',
                implode(', ', array_keys($data)),
            ));
        }
        return $this->StaticConstructor === null
            ? new $this->Service(...($args ?? []))
            : $this->Service::{$this->StaticConstructor}(...($args ?? []));
    }

    /**
     * @internal
     *
     * @param mixed[] $arguments
     * @return static
     */
    final public function __call(string $name, array $arguments)
    {
        if (
            ($this->Terminators[$name] ?? null)
            || ($this->Terminators[($this->Normaliser)($name, false)] ?? null)
        ) {
            return $this->build()->{$name}(...$arguments);
        }

        return $this->withValueB($name, $arguments ? $arguments[0] : true);
    }

    /**
     * @param mixed $value
     * @return static
     */
    final protected function withValueB(string $name, $value)
    {
        $data = $this->Data;
        $data[($this->Normaliser)($name)] = $value;
        return $this->with('Data', $data);
    }

    /**
     * @template TValue
     *
     * @param TValue $variable
     * @return static
     */
    final protected function withRefB(string $name, &$variable)
    {
        $data = $this->Data;
        $data[($this->Normaliser)($name)] = &$variable;
        return $this->with('Data', $data);
    }
}
