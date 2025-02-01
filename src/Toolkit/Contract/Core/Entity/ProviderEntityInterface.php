<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

use Salient\Contract\Core\Provider\ProviderContextInterface;
use Salient\Contract\Core\Provider\ProviderInterface;

/**
 * A generic entity serviced by a provider
 *
 * @template TProvider of ProviderInterface
 * @template TContext of ProviderContextInterface
 *
 * @extends Providable<TProvider,TContext>
 */
interface ProviderEntityInterface extends
    EntityInterface,
    Providable {}
