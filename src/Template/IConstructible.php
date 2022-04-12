<?php

declare(strict_types=1);

namespace Lkrms\Template;

/**
 * Can be instantiated from an associative array
 *
 * @package Lkrms
 * @see TConstructible
 */
interface IConstructible
{
    /**
     *
     * @param array<string,mixed> $array
     * @param callable|null $callback
     * @return static
     */
    public static function from(array $array, callable $callback = null);

    /**
     *
     * @param array<int,array<string,mixed>> $arrays
     * @param callable|null $callback
     * @return static[]
     */
    public static function listFrom(array $arrays, callable $callback = null): array;
}

