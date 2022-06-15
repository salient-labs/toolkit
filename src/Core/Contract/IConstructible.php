<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

use Psr\Container\ContainerInterface as Container;

/**
 * Converts arrays to instances
 *
 */
interface IConstructible
{
    /**
     * @param null|Container $container
     * @param array $data
     * @param callable|null $callback
     * @param array<int|string,int|string>|null $keyMap
     * @return static
     */
    public static function from(?Container $container, array $data, callable $callback = null, array $keyMap = null);

    /**
     * @param null|Container $container
     * @param iterable<array> $list
     * @param callable|null $callback
     * @param array<int|string,int|string>|null $keyMap
     * @return iterable<static>
     */
    public static function listFrom(?Container $container, iterable $list, callable $callback = null, array $keyMap = null): iterable;

}
