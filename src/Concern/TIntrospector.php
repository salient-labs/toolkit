<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IContainer;
use Lkrms\Support\IntrospectionClass;

/**
 * @template TClass of object
 * @template TIntrospectionClass of IntrospectionClass
 */
trait TIntrospector
{
    /**
     * @var TIntrospectionClass
     */
    protected $_Class;

    /**
     * @var string|null
     */
    protected $_Service;

    /**
     * @var array<string,TIntrospectionClass>
     */
    private static $IntrospectionClasses = [];

    /**
     * @param class-string<TClass> $class
     * @return TIntrospectionClass
     */
    abstract protected function getIntrospectionClass(string $class): IntrospectionClass;

    /**
     * Get an Introspector for a container-bound service
     *
     * Uses `$container` to resolve `$service` to a concrete class and returns
     * an {@see \Lkrms\Support\Introspector} for it.
     *
     * @template T of object
     * @param class-string<T> $service
     * @phpstan-return self<T,TIntrospectionClass>
     */
    public static function getService(IContainer $container, string $service): self
    {
        /** @var self<T,TIntrospectionClass> */
        $instance = new self($container->getName($service));
        $instance->_Service = $service;

        return $instance;
    }

    /**
     * Get an Introspector for a class
     *
     * @template T of object
     * @param class-string<T> $class
     * @phpstan-return self<T,TIntrospectionClass>
     */
    public static function get(string $class): self
    {
        /** @var self<T,TIntrospectionClass> */
        $instance = new self($class);

        return $instance;
    }

    /**
     * @param class-string<TClass> $class
     */
    private function __construct(string $class)
    {
        $_class = strtolower($class);
        $this->_Class =
            (self::$IntrospectionClasses[$_class] ?? null)
                ?: (self::$IntrospectionClasses[$_class] =
                    $this->getIntrospectionClass($class));
    }
}
