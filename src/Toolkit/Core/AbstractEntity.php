<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IProviderEntity;
use Lkrms\Support\ProviderContext;

/**
 * Base class for entities
 *
 * @implements IProviderEntity<Provider,ProviderContext<Provider,self>>
 */
abstract class Entity implements IProviderEntity {}
