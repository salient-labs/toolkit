<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Creates instances of itself from arrays
 *
 */
interface IConstructible
{
    /**
     * @param array<int|string,int|string|array<int,int|string>>|null $keyMap
     * @return static
     */
    public static function from(?IContainer $container, array $data, callable $callback = null, array $keyMap = null);

    /**
     * @param iterable<array> $list
     * @param array<int|string,int|string|array<int,int|string>>|null $keyMap
     * @return iterable<static>
     */
    public static function listFrom(?IContainer $container, iterable $list, callable $callback = null, array $keyMap = null): iterable;

}