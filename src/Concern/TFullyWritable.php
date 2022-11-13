<?php

declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Extends TWritable to write all protected properties by default
 *
 * @see TWritable
 */
trait TFullyWritable
{
    use TWritable;

    public static function getWritable(): array
    {
        return ["*"];
    }
}
