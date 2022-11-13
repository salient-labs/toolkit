<?php

declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Extends TReadable to read all protected properties by default
 *
 * @see TReadable
 */
trait TFullyReadable
{
    use TReadable;

    public static function getReadable(): array
    {
        return ["*"];
    }
}
