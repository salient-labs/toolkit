<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Closure;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IImmutable;
use Lkrms\Facade\Console;
use Lkrms\Support\Introspector;
use UnexpectedValueException;

/**
 * A fluent interface for creating instances of an underlying class
 *
 * The global container will be used to instantiate the underlying class unless
 * another container is passed to the builder, e.g.:
 *
 * ```php
 * $instance        = MyClassBuilder::build()->go();
 * $anotherInstance = MyClassBuilder::build($this->container())->go();
 * ```
 *
 * A `RuntimeException` will be thrown if no service container is available.
 *
 * @template TClass of object
 */
abstract class Builder extends FluentInterface implements IImmutable
{
    /**
     * Get the name of the underlying class
     *
     * @return class-string<TClass>
     */
    abstract protected static function getClassName(): string;

    /**
     * Get the name of the static method that returns a new builder
     *
     * The default method name is "build". Override
     * {@see Builder::getStaticBuilder()} to change it.
     */
    protected static function getStaticBuilder(): string
    {
        return 'build';
    }

    /**
     * Get the name of the method that returns a value applied to the builder
     *
     * The default method name is "get". Override
     * {@see Builder::getValueGetter()} to change it.
     */
    protected static function getValueGetter(): string
    {
        return 'get';
    }

    /**
     * Get the name of the method that returns true if a value has been applied
     * to the builder
     *
     * The default method name is "isset". Override
     * {@see Builder::getValueChecker()} to change it.
     */
    protected static function getValueChecker(): string
    {
        return 'isset';
    }

    /**
     * Get the name of the method that returns a new instance of the underlying
     * class and terminates the fluent interface
     *
     * The default method name is "go". Override {@see Builder::getTerminator()}
     * to change it.
     */
    protected static function getTerminator(): string
    {
        return 'go';
    }

    /**
     * Get the name of the static method that resolves a builder or an instance
     * of the underlying class to an instance of the underlying class
     *
     * The default method name is "resolve". Override
     * {@see Builder::getStaticResolver()} to change it.
     */
    protected static function getStaticResolver(): string
    {
        return 'resolve';
    }

    /**
     * @var IContainer
     */
    private $Container;

    /**
     * @var Introspector
     */
    private $Introspector;

    /**
     * @var Closure
     */
    private $Closure;

    /**
     * @var array<string,mixed>
     */
    private $Data = [];

    final public function __construct(?IContainer $container = null)
    {
        $this->Container = $container ?: Container::requireGlobalContainer();
        $this->Introspector = Introspector::getService($this->Container, static::getClassName());
        $this->Closure = $this->Introspector->getCreateFromClosure(true);
    }

    /**
     * @internal
     */
    final public static function __callStatic(string $name, array $arguments)
    {
        if (static::getStaticBuilder() === $name) {
            if (count($arguments) > 1) {
                throw new UnexpectedValueException('Invalid arguments');
            }
            if ($arguments && !($arguments[0] instanceof IContainer)) {
                throw new UnexpectedValueException('Argument #1 ($container) does not implement ' . IContainer::class);
            }

            return new static($arguments[0] ?? null);
        }
        if (static::getStaticResolver() === $name) {
            if (count($arguments) !== 1 || !is_object($arguments[0])) {
                throw new UnexpectedValueException('Invalid arguments');
            }
            $obj = $arguments[0];
            if ($obj instanceof self) {
                $obj = $obj->{$obj->getTerminator()}();
            }
            if (!is_a($obj, static::getClassName())) {
                throw new UnexpectedValueException('Argument #1 ($object) does not resolve to a ' . static::getClassName());
            }

            return $obj;
        }
        Console::debugOnce(sprintf('%s instantiated by deprecated static call:', static::class), $name);

        return (new static())->{$name}(...$arguments);
    }

    /**
     * @internal
     */
    final public function __call(string $name, array $arguments)
    {
        if (static::getValueGetter() === $name) {
            if (count($arguments) !== 1 || !is_string($arguments[0]) || !$arguments[0]) {
                throw new UnexpectedValueException(sprintf('Invalid arguments to %s::%s(string $name)', static::class, $name));
            }

            return $this->Data[$this->Introspector->maybeNormalise($arguments[0])] ?? null;
        }
        if (static::getValueChecker() === $name) {
            if (count($arguments) !== 1 || !is_string($arguments[0]) || !$arguments[0]) {
                throw new UnexpectedValueException(sprintf('Invalid arguments to %s::%s(string $name)', static::class, $name));
            }

            return array_key_exists($this->Introspector->maybeNormalise($arguments[0]), $this->Data);
        }
        if (static::getTerminator() === $name) {
            return ($this->Closure)($this->Data, $this->Container);
        }
        if (count($arguments) > 1) {
            throw new UnexpectedValueException('Invalid arguments');
        }

        return $this->getWithValue(
            $name,
            array_key_exists(0, $arguments)
                ? $arguments[0]
                : true
        );
    }

    /**
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
