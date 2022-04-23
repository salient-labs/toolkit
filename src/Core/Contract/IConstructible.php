<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

/**
 * Converts arrays to instances
 *
 * @package Lkrms
 */
interface IConstructible
{
    /**
     *
     * @param array<string,mixed> $data
     * @return static
     */
    public static function fromArray(array $data);

    /**
     *
     * @param array $data
     * @param callable $callback
     * @return static
     */
    public static function fromArrayVia(array $data, callable $callback);

    /**
     *
     * @param array $data
     * @param array<int|string,int|string> $keyMap
     * @return static
     */
    public static function fromMappedArray(array $data, array $keyMap);

    /**
     *
     * @param array<int,array<string,mixed>> $list
     * @return static[]
     */
    public static function listFromArrays(array $list): array;

    /**
     *
     * @param array[] $list
     * @param callable $callback
     * @return static[]
     */
    public static function listFromArraysVia(array $list, callable $callback): array;

    /**
     *
     * @param array[] $list
     * @param array<int|string,int|string> $keyMap
     * @return static[]
     */
    public static function listFromMappedArrays(array $list, array $keyMap): array;
}
