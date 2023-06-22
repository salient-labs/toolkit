<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * An arbitrary entity that can be instantiated by an IProvider
 *
 * @template TProvider of IProvider
 * @template TProviderContext of IProviderContext
 * @extends IProvidable<TProvider,TProviderContext>
 */
interface IProviderEntity extends IEntity, IProvidable {}
