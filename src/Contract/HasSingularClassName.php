<?php

declare(strict_types=1);

namespace Lkrms\Contract;

interface HasSingularClassName
{
    /**
     * Get the plural form of the class name
     *
     * The return value of `Faculty::getPluralClassName()`, for example, should
     * be `Faculties`.
     *
     * @return string
     */
    public static function getPluralClassName(): string;

}
