<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Represents the state of an external entity
 *
 * Instances are bound to their {@see IProvider} after being instantiated by its
 * service container.
 */
interface IProvidable
{
    /**
     * Get the provider servicing this entity
     *
     */
    public function getProvider(): ?IProvider;

    /**
     * Bind the entity to its provider
     *
     * Calling this method more than once per instance should raise a
     * `RuntimeException`.
     *
     * @return $this
     */
    public function setProvider(IProvider $provider);

    /**
     * @param array<int|string,int|string|array<int,int|string>>|null $keyMap
     * @return static
     */
    public static function fromProvider(IProvider $provider, array $data, callable $callback = null, array $keyMap = null);

    /**
     * @param iterable<array> $list
     * @param array<int|string,int|string|array<int,int|string>>|null $keyMap
     * @return iterable<static>
     */
    public static function listFromProvider(IProvider $provider, iterable $list, callable $callback = null, array $keyMap = null): iterable;

}
