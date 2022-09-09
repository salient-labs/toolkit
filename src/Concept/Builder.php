<?php

declare(strict_types=1);

namespace Lkrms\Concept;

use Closure;
use Lkrms\Container\Container;
use Lkrms\Support\ClosureBuilder;
use RuntimeException;
use UnexpectedValueException;

/**
 * Provides a fluent interface for creating instances of an underlying class
 *
 */
abstract class Builder
{
    /**
     * Get the name of the underlying class
     *
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
     * class, terminating the fluent interface
     *
     * The default method name is "go". Override {@see Builder::getTerminator()}
     * to change it.
     */
    protected static function getTerminator(): string
    {
        return "go";
    }

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

    final public function __construct()
    {
        if (Container::hasGlobalContainer())
        {
            $this->ClosureBuilder = ClosureBuilder::getBound(Container::getGlobalContainer(), static::getClassName());
        }
        else
        {
            $this->ClosureBuilder = ClosureBuilder::get(static::getClassName());
        }
        $this->Closure = $this->ClosureBuilder->getCreateFromClosure(true);
    }

    /**
     * @internal
     */
    final public static function __callStatic(string $name, array $arguments)
    {
        if (static::getStaticBuilder() === $name)
        {
            return new static();
        }
        throw new RuntimeException("Invalid method: $name");
    }

    /**
     * @internal
     */
    final public function __call(string $name, array $arguments)
    {
        if (static::getTerminator() === $name)
        {
            return ($this->Closure)($this->Data);
        }
        if (count($arguments) !== 1)
        {
            throw new UnexpectedValueException("Invalid arguments");
        }
        $this->Data[$this->ClosureBuilder->maybeNormaliseProperty($name)] = $arguments[0];

        return $this;
    }

}
