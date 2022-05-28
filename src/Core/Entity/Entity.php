<?php

declare(strict_types=1);

namespace Lkrms\Core\Entity;

use Lkrms\Core\Contract\IConstructible;
use Lkrms\Core\Contract\IExtensible;
use Lkrms\Core\Contract\IGettable;
use Lkrms\Core\Contract\IResolvable;
use Lkrms\Core\Contract\ISettable;
use Lkrms\Core\Mixin\TConstructible;
use Lkrms\Core\Mixin\TExtensible;
use Lkrms\Core\Mixin\TFullyGettable;
use Lkrms\Core\Mixin\TFullySettable;
use Lkrms\Core\Mixin\TResolvable;
use Lkrms\Util\Convert;

/**
 * Base class for entities
 *
 */
abstract class Entity implements IGettable, ISettable, IResolvable, IExtensible
{
    use TFullyGettable, TFullySettable, TResolvable, TExtensible;

    /**
     * Return the plural of the class name
     *
     * e.g. `Faculty::getPlural()` should return `Faculties`.
     *
     * Override if needed.
     *
     * @return string
     */
    public static function getPlural(): string
    {
        return Convert::nounToPlural(Convert::classToBasename(static::class));
    }
}
