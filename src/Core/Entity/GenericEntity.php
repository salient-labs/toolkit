<?php

declare(strict_types=1);

namespace Lkrms\Core\Entity;

use Lkrms\Core\Contract\IConstructible;
use Lkrms\Core\Mixin\TConstructible;

/**
 * Base class for entities that implement IConstructible
 *
 */
abstract class GenericEntity extends Entity implements IConstructible
{
    use TConstructible;

}
