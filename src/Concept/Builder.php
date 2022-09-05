<?php

declare(strict_types=1);

namespace Lkrms\Concept;

use Closure;
use Lkrms\Container\Container;
use Lkrms\Support\ClosureBuilder;
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
     * @return static
     */
    final public static function build()
    {
        return new static();
    }

    /**
     * @internal
     * @return $this
     */
    final public function __call(string $name, array $arguments)
    {
        if (count($arguments) !== 1)
        {
            throw new UnexpectedValueException("Invalid arguments");
        }
        $this->Data[$this->ClosureBuilder->maybeNormaliseProperty($name)] = $arguments[0];
        return $this;
    }

    /**
     * @internal
     */
    final public function get()
    {
        return ($this->Closure)($this->Data);
    }

}
