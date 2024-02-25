<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Core\Contract\ProvidableEntityInterface;

/**
 * Base class for provider-serviced entities
 *
 * @api
 *
 * @implements ProvidableEntityInterface<AbstractProvider,ProviderContext<AbstractProvider,self>>
 */
abstract class AbstractEntity implements ProvidableEntityInterface {}
