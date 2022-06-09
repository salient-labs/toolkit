<?php

declare(strict_types=1);

namespace Lkrms\Core\Entity;

use Lkrms\Core\Contract\ClassNameIsSingular;
use Lkrms\Core\Contract\IConstructibleByProvider;
use Lkrms\Core\Contract\IExtensible;
use Lkrms\Core\Contract\IGettable;
use Lkrms\Core\Contract\IResolvable;
use Lkrms\Core\Contract\ISettable;
use Lkrms\Core\Mixin\TConstructibleByProvider;
use Lkrms\Core\Mixin\TExtensible;
use Lkrms\Core\Mixin\TFullyGettable;
use Lkrms\Core\Mixin\TFullySettable;
use Lkrms\Core\Mixin\TPluralClassName;
use Lkrms\Core\Mixin\TResolvable;

/**
 * Base class for entities instantiated by an IProvider
 *
 * @see \Lkrms\Core\Contract\IProvider
 */
abstract class ProviderEntity implements IConstructibleByProvider, IGettable, ISettable, IResolvable, IExtensible, ClassNameIsSingular
{
    use TConstructibleByProvider, TFullyGettable, TFullySettable, TResolvable, TExtensible, TPluralClassName
    {
        TConstructibleByProvider::__clone as _cloneTConstructibleByProvider;
        TExtensible::__clone as _cloneTExtensible;
    }

    public function __clone()
    {
        $this->_cloneTConstructibleByProvider();
        $this->_cloneTExtensible();
    }

}
