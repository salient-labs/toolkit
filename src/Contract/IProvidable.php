<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Represents the state of an external entity
 *
 * - Instances are bound to the {@see IProvider} servicing them and are spawned
 *   by its service container
 * - Calling {@see IProvidable::setProvider()} more than once per instance
 *   should raise a {@see RuntimeException}
 */
interface IProvidable
{
    public function getProvider(): ?IProvider;

    /**
     * @return $this
     */
    public function setProvider(IProvider $provider);

    /**
     * @param IProvider $provider
     * @param array $data
     * @param callable|null $callback
     * @param array<int|string,int|string>|null $keyMap
     * @return static
     */
    public static function fromProvider(IProvider $provider, array $data, callable $callback = null, array $keyMap = null);

    /**
     * @param IProvider $provider
     * @param iterable<array> $list
     * @param callable|null $callback
     * @param array<int|string,int|string>|null $keyMap
     * @return iterable<static>
     */
    public static function listFromProvider(IProvider $provider, iterable $list, callable $callback = null, array $keyMap = null): iterable;

}
