<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IImmutable;
use Lkrms\Support\Introspector;
use Lkrms\Support\ProviderContext;
use LogicException;

/**
 * Base class for object builders
 *
 * For example:
 *
 * ```php
 * <?php
 * class Foo implements ProvidesBuilder
 * {
 *     use HasBuilder;
 *
 *     protected $Bar;
 *
 *     public function __construct($bar)
 *     {
 *         $this->Bar = $bar;
 *     }
 *
 *     public static function getBuilder(): string
 *     {
 *         return FooBuilder::class;
 *     }
 * }
 *
 * class FooBuilder extends Builder
 * {
 *     protected static function getService(): string
 *     {
 *         return Foo::class;
 *     }
 *
 *     protected static function getTerminators(): array
 *     {
 *         return [];
 *     }
 * }
 *
 * $foo = Foo::build()
 *     ->bar('baz')
 *     ->go();
 * ```
 *
 * @template TClass of object
 */
abstract class Builder extends FluentInterface implements IImmutable
{
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

    protected IContainer $Container;

    /**
     * @var Introspector<object,Provider,Entity,ProviderContext>
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
    final public function __construct(?IContainer $container = null)
    {
        $this->Container = $container ?? Container::getGlobalContainer();
        $this->Introspector = Introspector::getService($this->Container, static::getService());
        foreach (static::getTerminators() as $terminator) {
            $this->Terminators[$terminator] = true;
            $this->Terminators[$this->Introspector->maybeNormalise($terminator)] = true;
        }
    }

    /**
     * Get a new builder
     *
     * Syntactic sugar for:
     *
     * ```php
     * <?php
     * new static()
     * ```
     *
     * @return static
     */
    final public static function build(?IContainer $container = null)
    {
        return new static($container);
    }

    /**
     * Get an instance from an optionally terminated builder
     *
     * Syntactic sugar for:
     *
     * ```php
     * <?php
     * $object instanceof static ? $object->go() : $object
     * ```
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
     * @return $this
     */
    final public function __call(string $name, array $arguments)
    {
        if (($this->Terminators[$name] ?? null) ||
                ($this->Terminators[$this->Introspector->maybeNormalise($name)] ?? null)) {
            return $this->go()->{$name}(...$arguments);
        }

        if (count($arguments) > 1) {
            throw new LogicException('Invalid arguments');
        }

        return $this->getWithValue(
            $name,
            array_key_exists(0, $arguments)
                ? $arguments[0]
                : true
        );
    }

    /**
     * @param mixed $value
     * @return $this
     */
    final protected function getWithValue(string $name, $value)
    {
        $clone = clone $this;
        $name = $clone->Introspector->maybeNormalise($name);
        $clone->Data[$name] = $value;

        return $clone;
    }

    /**
     * @param mixed $variable
     * @return $this
     */
    final protected function getWithReference(string $name, &$variable)
    {
        $clone = clone $this;
        $name = $clone->Introspector->maybeNormalise($name);
        $clone->Data[$name] = &$variable;

        return $clone;
    }
}
