<?php

declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\HasSingularClassName;
use Lkrms\Contract\IConstructible;
use Lkrms\Contract\IExtensible;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\IResolvable;
use Lkrms\Contract\IWritable;
use Lkrms\Concern\TConstructible;
use Lkrms\Concern\TExtensible;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Concern\TFullyWritable;
use Lkrms\Concern\TPluralClassName;
use Lkrms\Concern\TResolvable;

/**
 * Base class for entities
 *
 */
abstract class Entity implements IConstructible, IReadable, IWritable, IResolvable, IExtensible, HasSingularClassName
{
    use TConstructible, TFullyReadable, TFullyWritable, TResolvable, TExtensible, TPluralClassName;

}
