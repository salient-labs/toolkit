<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * A generic entity serviced by a provider
 *
 * @template TProvider of IProvider
 * @template TContext of IProviderContext
 *
 * @extends IProvidable<TProvider,TContext>
 */
interface IProviderEntity extends
    IEntity,
    IProvidable {}
