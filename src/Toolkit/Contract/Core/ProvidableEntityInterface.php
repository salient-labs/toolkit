<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * A generic entity serviced by a provider
 *
 * @template TProvider of ProviderInterface
 * @template TContext of ProviderContextInterface
 *
 * @extends Providable<TProvider,TContext>
 */
interface ProvidableEntityInterface extends
    EntityInterface,
    Providable {}
