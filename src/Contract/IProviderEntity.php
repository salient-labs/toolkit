<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * An entity that can be instantiated by a provider
 *
 * @template TProvider of IProvider
 * @template TContext of IProviderContext
 *
 * @extends IProvidable<TProvider,TContext>
 */
interface IProviderEntity extends IEntity, IProvidable {}
