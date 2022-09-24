<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Closure;

/**
 * Can form part of a pipeline
 *
 * @see IPipeline
 */
interface IPipe
{
    public function handle($payload, Closure $next, ...$args);

}
