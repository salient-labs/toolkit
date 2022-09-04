<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Can be created by an IProvider to represent an external entity
 *
 */
interface IProvidable
{
    /**
     * Get the provider servicing this entity
     *
     */
    public function provider(): ?IProvider;

    /**
     * Called immediately after instantiation by a provider's service container
     *
     * @throws \RuntimeException if the provider has already been set for this
     * instance.
     */
    public function setProvider(IProvider $provider): void;

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
