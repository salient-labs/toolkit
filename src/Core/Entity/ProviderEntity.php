<?php

declare(strict_types=1);

namespace Lkrms\Core\Entity;

use Lkrms\Core\Contract\ClassNameIsSingular;
use Lkrms\Core\Contract\IExtensible;
use Lkrms\Core\Contract\IGettable;
use Lkrms\Core\Contract\IProvidable;
use Lkrms\Core\Contract\IResolvable;
use Lkrms\Core\Contract\ISettable;
use Lkrms\Core\Mixin\TExtensible;
use Lkrms\Core\Mixin\TFullyGettable;
use Lkrms\Core\Mixin\TFullySettable;
use Lkrms\Core\Mixin\TPluralClassName;
use Lkrms\Core\Mixin\TProvidable;
use Lkrms\Core\Mixin\TResolvable;

/**
 * Base class for entities instantiated by an IProvider
 *
 * @see \Lkrms\Core\Contract\IProvider
 */
abstract class ProviderEntity implements IProvidable, IGettable, ISettable, IResolvable, IExtensible, ClassNameIsSingular
{
    use TProvidable, TFullyGettable, TFullySettable, TResolvable, TExtensible, TPluralClassName
    {
        TProvidable::__clone as _cloneTProvidable;
        TExtensible::__clone as _cloneTExtensible;
    }

    public function __clone()
    {
        $this->_cloneTProvidable();
        $this->_cloneTExtensible();
    }

}
