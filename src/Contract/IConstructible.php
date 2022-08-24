<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Psr\Container\ContainerInterface as Container;

/**
 * Creates instances of itself from arrays
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
