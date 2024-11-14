<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Container\RequiresContainer;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\BuilderInterface;
use Salient\Core\Concern\HasChainableMethods;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use InvalidArgumentException;

/**
 * Base class for builders
 *
 * @api
 *
 * @template TClass of object
 *
 * @implements BuilderInterface<TClass>
 */
abstract class AbstractBuilder implements BuilderInterface
{
    use HasChainableMethods;
    use RequiresContainer;

    /**
     * Get the class to build
     *
     * @return class-string<TClass>
     */
    abstract protected static function getService(): string;

    /**
     * Get methods to forward to a new instance of the service class
     *
     * @return string[]
     */
    protected static function getTerminators(): array
    {
        return [];
    }

    /** @todo Decouple from the service container */
    protected ContainerInterface $Container;
    /** @var Introspector<object,AbstractProvider,AbstractEntity,ProviderContext<AbstractProvider,AbstractEntity>> */
    private Introspector $Introspector;
    /** @var array<string,true> */
    private array $Terminators = [];
    /** @var array<string,mixed> */
    private array $Data = [];

    /**
     * Creates a new builder
     */
    final public function __construct(?ContainerInterface $container = null)
    {
        $this->Container = self::requireContainer($container);
        $this->Introspector = Introspector::getService($this->Container, static::getService());
        foreach (static::getTerminators() as $terminator) {
            $this->Terminators[$terminator] = true;
            $this->Terminators[$this->Introspector->maybeNormalise($terminator)] = true;
        }
    }

    /**
     * @inheritDoc
     */
    final public static function create(?ContainerInterface $container = null)
    {
        return new static($container);
    }

    /**
     * @inheritDoc
     */
    final public static function resolve($object)
    {
        if ($object instanceof static) {
            return $object->build();
        }

        if (is_a($object, static::getService())) {
            return $object;
        }

        throw new InvalidArgumentTypeException(
            1,
            'object',
            static::class . '|' . static::getService(),
            $object,
        );
    }

    /**
     * Get the builder's container
     */
    final public function getContainer(): ContainerInterface
    {
        return $this->Container;
    }

    /**
     * @inheritDoc
     */
    final public function getB(string $name)
    {
        return $this->Data[$this->Introspector->maybeNormalise($name)] ?? null;
    }

    /**
     * @inheritDoc
     */
    final public function issetB(string $name): bool
    {
        return array_key_exists($this->Introspector->maybeNormalise($name), $this->Data);
    }

    /**
     * @inheritDoc
     */
    final public function unsetB(string $name)
    {
        $name = $this->Introspector->maybeNormalise($name);
        if (!array_key_exists($name, $this->Data)) {
            return $this;
        }
        $clone = clone $this;
        unset($clone->Data[$name]);
        return $clone;
    }

    /**
     * @inheritDoc
     */
    final public function build()
    {
        /** @var TClass */
        return $this->Introspector->getCreateFromClosure(true)($this->Data, $this->Container);
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
            || ($this->Terminators[$this->Introspector->maybeNormalise($name)] ?? null)
        ) {
            return $this->build()->{$name}(...$arguments);
        }

        $count = count($arguments);
        if ($count > 1) {
            throw new InvalidArgumentException('Too many arguments');
        }

        return $this->withValueB($name, $count ? $arguments[0] : true);
    }

    /**
     * @param mixed $value
     * @return static
     */
    final protected function withValueB(string $name, $value)
    {
        $name = $this->Introspector->maybeNormalise($name);
        if (array_key_exists($name, $this->Data) && $this->Data[$name] === $value) {
            return $this;
        }
        $clone = clone $this;
        $clone->Data[$name] = $value;
        return $clone;
    }

    /**
     * @template TValue
     *
     * @param TValue $variable
     * @return static
     */
    final protected function withRefB(string $name, &$variable)
    {
        $name = $this->Introspector->maybeNormalise($name);
        $clone = clone $this;
        $clone->Data[$name] = &$variable;
        return $clone;
    }

    /**
     * @deprecated Use {@see build()} instead
     *
     * @return TClass
     *
     * @codeCoverageIgnore
     */
    final public function go()
    {
        return $this->build();
    }
}
