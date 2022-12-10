<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TImmutable;
use Lkrms\Contract\IPipelineImmutable;

/**
 * Sends a payload through an immutable series of pipes to a destination
 *
 */
class PipelineImmutable extends Pipeline implements IPipelineImmutable
{
    use TImmutable;
}
