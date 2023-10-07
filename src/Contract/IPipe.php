<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Closure;

/**
 * Forms part of a pipeline
 *
 * @template TInput
 * @template TOutput
 * @template TArgument
 *
 * @see IPipeline
 */
interface IPipe
{
    /**
     * @param TInput|TOutput $payload
     * @param IPipeline<TInput,TOutput,TArgument> $pipeline
     * @param TArgument $arg
     * @return TInput|TOutput
     */
    public function handle($payload, Closure $next, IPipeline $pipeline, $arg);
}
