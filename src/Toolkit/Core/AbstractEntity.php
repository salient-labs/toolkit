<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\Provider\ProvidableEntityInterface;

/**
 * Base class for provider-serviced entities
 *
 * @api
 *
 * @implements ProvidableEntityInterface<AbstractProvider,ProviderContext<AbstractProvider,self>>
 */
abstract class AbstractEntity implements ProvidableEntityInterface {}
