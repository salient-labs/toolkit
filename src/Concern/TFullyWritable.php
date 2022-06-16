<?php

declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Extends TSettable to write all protected properties by default
 *
 * @see TWritable
 */
trait TFullyWritable
{
    use TWritable;

    /**
     * @return string[]
     */
    public static function getSettable(): array
    {
        return ["*"];
    }
}
