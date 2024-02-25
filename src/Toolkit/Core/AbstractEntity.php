<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Core\Contract\IProviderEntity;
use Salient\Core\ProviderContext;

/**
 * Base class for entities
 *
 * @implements IProviderEntity<Provider,ProviderContext<Provider,self>>
 */
abstract class Entity implements IProviderEntity {}
