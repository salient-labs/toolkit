<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Container;

/**
 * Implements IBindable to provide services and bind them to containers
 *
 * @see \Lkrms\Contract\IBindable
 * @see \Lkrms\Concern\TBindableSingleton
 */
trait TBindable
{
    /**
     * @var Container
     */
    private $Container;

    public function __construct(Container $container)
    {
        $this->Container = $container;
    }

    final public static function bind(Container $container)
    {
        $container->bind(static::class);
    }

    public static function bindServices(Container $container, string ...$interfaces)
    {
    }

    public static function bindServicesExcept(Container $container, string ...$interfaces)
    {
    }

    public static function bindConcrete(Container $container)
    {
    }

    final public function invokeInBoundContainer(callable $callback, Container $container = null)
    {
        if (!$container)
        {
            $container = $this->Container;
        }
        $container->push();
        try
        {
            static::bind($container);
            static::bindServices($container);
            static::bindConcrete($container);
            $clone = clone $container;
            $container->bindContainer($clone);
            return $callback($clone);
        }
        finally
        {
            $container->pop();
        }
    }

    final public function container(): Container
    {
        return $this->Container;
    }
}
