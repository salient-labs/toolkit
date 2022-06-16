<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Util\Convert;

/**
 * Implements ClassNameIsSingular
 *
 * @see \Lkrms\Contract\HasSingularClassName
 */
trait TPluralClassName
{
    public static function getPluralClassName(): string
    {
        return Convert::nounToPlural(Convert::classToBasename(static::class));
    }
}
