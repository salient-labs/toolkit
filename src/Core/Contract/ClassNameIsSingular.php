<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

interface ClassNameIsSingular
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
