<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

use Salient\Contract\Core\Provider\ProviderContextInterface;
use Salient\Contract\Core\Provider\ProviderInterface;

/**
 * @api
 *
 * @template TProvider of ProviderInterface
 * @template TContext of ProviderContextInterface
 *
 * @extends Providable<TProvider,TContext>
 */
interface ProviderEntityInterface extends
    EntityInterface,
    Providable {}
