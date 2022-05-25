<?php

declare(strict_types=1);

namespace Lkrms\Core\Mixin;

/**
 * Extends TSettable to write all protected properties by default
 *
 * @see TSettable
 */
trait TFullySettable
{
    use TSettable;

    /**
     * @return string[]
     */
    public static function getSettable(): array
    {
        return ["*"];
    }
}
