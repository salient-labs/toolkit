<?php

declare(strict_types=1);

namespace Lkrms\Concept;

use Closure;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IImmutable;
use Lkrms\Support\ClosureBuilder;
use UnexpectedValueException;

/**
 * A fluent interface for creating instances of an underlying class
 *
 * If a global container has been loaded, it will be used to instantiate the
 * underlying class unless another container is passed to the builder, e.g.:
 *
 * ```php
 * $instance      = MyClassBuilder::build()->go();
 * $boundInstance = MyClassBuilder::build($this->container())->go();
 * ```
 *
 * If no service container is located, instances are created directly.
 */
abstract class Builder implements IImmutable
{
    /**
     * Return the name of the underlying class
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
        return "build";
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
        return "go";
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
        return "resolve";
    }

    /**
     * @var IContainer|null
     */
    private $Container;

    /**
     * @var ClosureBuilder
     */
    private $ClosureBuilder;

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
        $this->Container      = $container;
        $this->ClosureBuilder = ClosureBuilder::maybeGetBound(
            $container ?: Container::maybeGetGlobalContainer(),
            static::getClassName()
        );
        $this->Closure = $this->ClosureBuilder->getCreateFromClosure(true);
    }

    /**
     * @internal
     */
    final public static function __callStatic(string $name, array $arguments)
    {
        if (static::getStaticBuilder() === $name)
        {
            if (count($arguments) > 1)
            {
                throw new UnexpectedValueException("Invalid arguments");
            }
            if ($arguments && !($arguments[0] instanceof IContainer))
            {
                throw new UnexpectedValueException('Argument #1 ($container) does not implement ' . IContainer::class);
            }

            return new static($arguments[0] ?? null);
        }
        if (static::getStaticResolver() === $name)
        {
            if (count($arguments) !== 1 || !is_object($arguments[0]))
            {
                throw new UnexpectedValueException("Invalid arguments");
            }
            $obj = $arguments[0];
            if ($obj instanceof self)
            {
                $obj = $obj->{$obj->getTerminator()}();
            }
            if (!is_a($obj, static::getClassName()))
            {
                throw new UnexpectedValueException('Argument #1 ($object) does not resolve to a ' . static::getClassName());
            }
            return $obj;
        }
        return (new static())->{$name}(...$arguments);
    }

    /**
     * @internal
     */
    final public function __call(string $name, array $arguments)
    {
        if (static::getTerminator() === $name)
        {
            return ($this->Closure)(
                $this->Data,
                $this->Container ?: Container::maybeGetGlobalContainer()
            );
        }
        if (count($arguments) > 1)
        {
            throw new UnexpectedValueException("Invalid arguments");
        }
        $clone = clone $this;
        $clone->Data[$clone->ClosureBuilder->maybeNormalise($name)] = (array_key_exists(0, $arguments)
            ? $arguments[0]
            : true);

        return $clone;
    }

}
