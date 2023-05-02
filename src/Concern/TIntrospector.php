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
     * @var TIntrospectionClass<TClass>
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
     * @return TIntrospectionClass<TClass>
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
     * @return self<T,TIntrospectionClass<T>>
     */
    public static function getService(IContainer $container, string $service)
    {
        /** @var self<T,TIntrospectionClass<T>> */
        $instance = new self($container->getName($service));
        $instance->_Service = $service;

        return $instance;
    }

    /**
     * Get an Introspector for a class
     *
     * @template T of object
     * @param class-string<T> $class
     * @return self<T,TIntrospectionClass<T>>
     */
    public static function get(string $class)
    {
        /** @var self<T,TIntrospectionClass<T>> */
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
