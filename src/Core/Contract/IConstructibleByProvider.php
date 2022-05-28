<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

/**
 * Converts backend data to instances
 *
 */
interface IConstructibleByProvider
{
    public function setProvider(IProvider $provider): void;

    public function getProvider(): IProvider;

    /**
     *
     * @param IProvider $provider
     * @param array<string,mixed> $data
     * @return static
     */
    public static function fromArray(IProvider $provider, array $data);

    /**
     *
     * @param IProvider $provider
     * @param array $data
     * @param callable $callback
     * @return static
     */
    public static function fromArrayVia(IProvider $provider, array $data, callable $callback);

    /**
     *
     * @param IProvider $provider
     * @param array $data
     * @param array<int|string,int|string> $keyMap
     * @return static
     */
    public static function fromMappedArray(IProvider $provider, array $data, array $keyMap);

    /**
     *
     * @param IProvider $provider
     * @param array $data
     * @param callable $callback
     * @param array<int|string,int|string> $keyMap
     * @return static
     */
    public static function fromMappedArrayVia(IProvider $provider, array $data, callable $callback, array $keyMap);

    /**
     *
     * @param IProvider $provider
     * @param array<int,array<string,mixed>> $list
     * @return static[]
     */
    public static function listFromArrays(IProvider $provider, array $list): array;

    /**
     *
     * @param IProvider $provider
     * @param array[] $list
     * @param callable $callback
     * @return static[]
     */
    public static function listFromArraysVia(IProvider $provider, array $list, callable $callback): array;

    /**
     *
     * @param IProvider $provider
     * @param array[] $list
     * @param array<int|string,int|string> $keyMap
     * @return static[]
     */
    public static function listFromMappedArrays(IProvider $provider, array $list, array $keyMap): array;

    /**
     *
     * @param IProvider $provider
     * @param array[] $list
     * @param callable $callback
     * @param array<int|string,int|string> $keyMap
     * @return static[]
     */
    public static function listFromMappedArraysVia(IProvider $provider, array $list, callable $callback, array $keyMap): array;

}
