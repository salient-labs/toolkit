<?php

declare(strict_types=1);

namespace Lkrms\Core;

use Lkrms\Core\Contract\IConstructible;
use Lkrms\Core\Contract\IExtensible;
use Lkrms\Core\Contract\IGettable;
use Lkrms\Core\Contract\IResolvable;
use Lkrms\Core\Contract\ISettable;
use Lkrms\Core\Mixin\TConstructible;
use Lkrms\Core\Mixin\TExtensible;
use Lkrms\Core\Mixin\TGettable;
use Lkrms\Core\Mixin\TResolvable;
use Lkrms\Core\Mixin\TSettable;
use Lkrms\Util\Convert;

/**
 * Base class for entities
 *
 * @package Lkrms
 */
abstract class Entity implements IConstructible, IGettable, ISettable, IResolvable, IExtensible
{
    use TConstructible, TGettable, TSettable, TResolvable, TExtensible;

    public static function getGettable(): array
    {
        return ["*"];
    }

    public static function getSettable(): array
    {
        return ["*"];
    }

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
