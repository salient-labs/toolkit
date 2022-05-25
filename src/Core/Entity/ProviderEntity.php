<?php

declare(strict_types=1);

namespace Lkrms\Core\Entity;

use Lkrms\Core\Contract\IConstructibleByProvider;
use Lkrms\Core\Mixin\TConstructibleByProvider;

/**
 * Base class for entities that implement IConstructibleByProvider
 *
 */
abstract class ProviderEntity extends Entity implements IConstructibleByProvider
{
    use TConstructibleByProvider;

}
