<?php

declare(strict_types=1);

namespace Lkrms\Core\Mixin;

use Lkrms\Util\Convert;

/**
 * Implements ClassNameIsSingular
 *
 * @see \Lkrms\Core\Contract\ClassNameIsSingular
 */
trait TPluralClassName
{
    public static function getPluralClassName(): string
    {
        return Convert::nounToPlural(Convert::classToBasename(static::class));
    }
}
