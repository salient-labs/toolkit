<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Sends a payload through an immutable series of pipes to a destination
 *
 */
interface IPipelineImmutable extends IPipeline, IImmutable
{
}
