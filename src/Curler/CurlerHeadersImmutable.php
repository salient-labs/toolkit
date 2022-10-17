<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Concern\TImmutable;
use Lkrms\Contract\IImmutable;

/**
 * An immutable collection of HTTP headers
 *
 */
final class CurlerHeadersImmutable extends CurlerHeaders implements IImmutable
{
    use TImmutable;

}
