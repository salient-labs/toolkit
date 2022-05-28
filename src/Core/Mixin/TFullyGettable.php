<?php

declare(strict_types=1);

namespace Lkrms\Core\Mixin;

/**
 * Extends TGettable to read all protected properties by default
 *
 * @see TGettable
 */
trait TFullyGettable
{
    use TGettable;

    /**
     * @return string[]
     */
    public static function getGettable(): array
    {
        return ["*"];
    }
}
