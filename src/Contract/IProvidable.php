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
     * Get the base class of this entity
     *
     * ```php
     * // First:
     * $this->app()->bind(Faculty::class, CustomFaculty::class);
     *
     * // Then, in an IProvider:
     * $faculty = $this->app()->get(Faculty::class)->setProvider($this, Faculty::class);
     *
     * // Finally:
     * print_r([
     *     "class"      => get_class($faculty),
     *     "base_class" => $faculty->providable(),
     * ]);
     * ```
     *
     * Because `$faculty` is a `CustomFaculty` created to satisfy a `Faculty`
     * request, the example above will output:
     *
     * ```
     * Array
     * (
     *     [class] => CustomFaculty
     *     [base_class] => Faculty
     * )
     * ```
     *
     */
    public function providable(): ?string;

    /**
     * Called immediately after instantiation by a provider's service container
     *
     * @param string $providable The name of the entity resolved to this
     * instance by the provider.
     * @return $this
     * @throws \RuntimeException if the provider has already been set for this
     * instance.
     */
    public function setProvider(IProvider $provider, string $providable);

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
