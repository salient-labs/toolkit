<?php declare(strict_types=1);

namespace Salient\Core\Provider;

use Salient\Contract\Core\Entity\ProviderEntityInterface;

/**
 * Base class for provider-serviced entities
 *
 * @api
 *
 * @implements ProviderEntityInterface<AbstractProvider,ProviderContext<AbstractProvider,self>>
 */
abstract class AbstractEntity implements ProviderEntityInterface {}
