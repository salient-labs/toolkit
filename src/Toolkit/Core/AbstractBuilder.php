<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Container\Container;
use Salient\Container\ContainerInterface;
use Salient\Core\Concern\HasChainableMethods;
use Salient\Core\Contract\Chainable;
use Salient\Core\Contract\Immutable;
use Salient\Core\AbstractEntity;
use Salient\Core\AbstractProvider;
use Salient\Core\Introspector;
use Salient\Core\ProviderContext;
use LogicException;

/**
 * Base class for builders
 *
 * @template TClass of object
 */
abstract class AbstractBuilder implements Chainable, Immutable
{
    use HasChainableMethods;

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

    protected ContainerInterface $Container;

    /**
     * @var Introspector<object,AbstractProvider,AbstractEntity,ProviderContext<AbstractProvider,AbstractEntity>>
     */
    private Introspector $Introspector;

    /**
     * @var array<string,true>
     */
    private array $Terminators = [];

    /**
     * @var array<string,mixed>
     */
    private array $Data = [];

    /**
     * Creates a new builder
     */
    final public function __construct(?ContainerInterface $container = null)
    {
        $this->Container = $container ?? Container::getGlobalContainer();
        $this->Introspector = Introspector::getService($this->Container, static::getService());
        foreach (static::getTerminators() as $terminator) {
            $this->Terminators[$terminator] = true;
            $this->Terminators[$this->Introspector->maybeNormalise($terminator)] = true;
        }
    }

    /**
     * Creates a new builder
     *
     * @return static
     */
    final public static function build(?ContainerInterface $container = null)
    {
        return new static($container);
    }

    /**
     * Get an instance from an optionally terminated builder
     *
     * @param static|TClass $object
     * @return TClass
     */
    final public static function resolve($object)
    {
        if ($object instanceof static) {
            return $object->go();
        }

        if (!is_a($object, static::getService())) {
            throw new LogicException(sprintf(
                'Invalid argument (%s|%s expected)',
                static::class,
                static::getService(),
            ));
        }

        return $object;
    }

    /**
     * Get a value applied to the builder
     *
     * @return mixed|null
     */
    final public function getB(string $name)
    {
        return $this->Data[$this->Introspector->maybeNormalise($name)] ?? null;
    }

    /**
     * True if a value has been applied to the builder
     */
    final public function issetB(string $name): bool
    {
        return array_key_exists($this->Introspector->maybeNormalise($name), $this->Data);
    }

    /**
     * Remove a value applied to the builder
     *
     * @return static
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
     * Get a new instance of the service class
     *
     * @return TClass
     */
    final public function go()
    {
        return ($this->Introspector->getCreateFromClosure(true))($this->Data, $this->Container);
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
            ($this->Terminators[$name] ?? null) ||
            ($this->Terminators[$this->Introspector->maybeNormalise($name)] ?? null)
        ) {
            return $this->go()->{$name}(...$arguments);
        }

        $count = count($arguments);
        if ($count > 1) {
            throw new LogicException('Invalid arguments');
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
     * @param mixed $variable
     * @return static
     */
    final protected function withRefB(string $name, &$variable)
    {
        $name = $this->Introspector->maybeNormalise($name);
        $clone = clone $this;
        $clone->Data[$name] = &$variable;
        return $clone;
    }
}
