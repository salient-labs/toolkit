<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\ArrayKeyConformity;

/**
 * Can be instantiated by an IProvider
 *
 * @template TProvider of IProvider
 * @template TProviderContext of IProviderContext
 */
interface IProvidable extends ReceivesService, ReturnsService
{
    /**
     * Get the provider servicing the entity
     *
     * @return TProvider|null
     */
    public function provider(): ?IProvider;

    /**
     * Get the context in which the entity is being serviced
     *
     * @return TProviderContext|null
     */
    public function context(): ?IProviderContext;

    /**
     * Get the context in which the entity is being serviced, or throw an
     * exception if no context has been set
     *
     * @return TProviderContext
     */
    public function requireContext(): IProviderContext;

    /**
     * Get the entity the instance was resolved from
     *
     * Consider the following scenario:
     *
     * - `Faculty` is a `SyncEntity` subclass and therefore implements
     *   `IProvidable`
     * - `CustomFaculty` is a subclass of `Faculty`
     * - `CustomFaculty` is bound to the service container as `Faculty`:
     *   ```php
     *   $this->app()->bind(Faculty::class, CustomFaculty::class);
     *   ```
     * - `$provider` implements `FacultyProvider`
     * - A `Faculty` object is requested from `$provider` for faculty #1:
     *   ```php
     *   $faculty = $provider->with(Faculty::class)->get(1);
     *   ```
     *
     * `$faculty` is now a `Faculty` service and an instance of `CustomFaculty`,
     * so this code:
     *
     * ```php
     * print_r([
     *     'class'   => get_class($faculty),
     *     'service' => $faculty->service(),
     * ]);
     * ```
     *
     * will produce the following output:
     *
     * ```
     * Array
     * (
     *     [class] => CustomFaculty
     *     [service] => Faculty
     * )
     * ```
     */
    public function service(): string;

    /**
     * Called immediately after instantiation by a provider's service container
     *
     * @param TProvider $provider
     * @return $this
     * @throws \RuntimeException if the instance already has a provider.
     */
    public function setProvider(IProvider $provider);

    /**
     * Called immediately after instantiation, then as needed by the provider
     *
     * @param TProviderContext $context
     * @return $this
     */
    public function setContext(IProviderContext $context);

    /**
     * @return static
     */
    public static function provide(array $data, IProvider $provider, ?IProviderContext $context = null);

    /**
     * @param iterable<array> $dataList
     * @return iterable<static>
     */
    public static function provideList(
        iterable $dataList,
        IProvider $provider,
        int $conformity = ArrayKeyConformity::NONE,
        ?IProviderContext $context = null
    ): iterable;
}
