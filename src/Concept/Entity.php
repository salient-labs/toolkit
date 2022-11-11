<?php

declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Concern\HasPluralClassName;
use Lkrms\Concern\TConstructible;
use Lkrms\Concern\TExtensible;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Concern\TFullyWritable;
use Lkrms\Concern\TResolvable;
use Lkrms\Contract\HasDateProperties;
use Lkrms\Contract\IConstructible;
use Lkrms\Contract\IExtensible;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\IResolvable;
use Lkrms\Contract\IWritable;

/**
 * Base class for entities
 *
 */
abstract class Entity implements IConstructible, IReadable, IWritable, IResolvable, IExtensible, HasDateProperties
{
    use TConstructible, TFullyReadable, TFullyWritable, TResolvable, TExtensible, HasPluralClassName;

}
