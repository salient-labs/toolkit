<?php

declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\HasSingularClassName;
use Lkrms\Contract\IExtensible;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\IProvidable;
use Lkrms\Contract\IResolvable;
use Lkrms\Contract\IWritable;
use Lkrms\Concern\TExtensible;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Concern\TFullyWritable;
use Lkrms\Concern\TPluralClassName;
use Lkrms\Concern\TProvidable;
use Lkrms\Concern\TResolvable;

/**
 * Base class for entities instantiated by an IProvider
 *
 * @see \Lkrms\Contract\IProvider
 */
abstract class ProviderEntity implements IProvidable, IReadable, IWritable, IResolvable, IExtensible, HasSingularClassName
{
    use TProvidable, TFullyReadable, TFullyWritable, TResolvable, TExtensible, TPluralClassName
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
