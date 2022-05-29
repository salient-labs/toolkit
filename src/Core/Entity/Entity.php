<?php

declare(strict_types=1);

namespace Lkrms\Core\Entity;

use Lkrms\Core\Contract\ClassNameIsSingular;
use Lkrms\Core\Contract\IConstructible;
use Lkrms\Core\Contract\IExtensible;
use Lkrms\Core\Contract\IGettable;
use Lkrms\Core\Contract\IResolvable;
use Lkrms\Core\Contract\ISettable;
use Lkrms\Core\Mixin\TConstructible;
use Lkrms\Core\Mixin\TExtensible;
use Lkrms\Core\Mixin\TFullyGettable;
use Lkrms\Core\Mixin\TFullySettable;
use Lkrms\Core\Mixin\TPluralClassName;
use Lkrms\Core\Mixin\TResolvable;

/**
 * Base class for entities
 *
 */
abstract class Entity implements IConstructible, IGettable, ISettable, IResolvable, IExtensible, ClassNameIsSingular
{
    use TConstructible, TFullyGettable, TFullySettable, TResolvable, TExtensible, TPluralClassName;

}
