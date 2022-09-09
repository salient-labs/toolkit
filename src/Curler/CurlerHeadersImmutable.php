<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Contract\IImmutable;

/**
 * An immutable collection of HTTP headers
 *
 */
class CurlerHeadersImmutable extends CurlerHeaders implements IImmutable
{
    public static function fromMutable(CurlerHeaders $headers): CurlerHeadersImmutable
    {
        return $headers->toImmutable();
    }

    protected function getMutable(): self
    {
        return clone $this;
    }

}
