<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Core\Contract\ProvidableEntityInterface;
use Salient\Core\ProviderContext;

/**
 * Base class for entities
 *
 * @implements ProvidableEntityInterface<AbstractProvider,ProviderContext<AbstractProvider,self>>
 */
abstract class AbstractEntity implements ProvidableEntityInterface {}
