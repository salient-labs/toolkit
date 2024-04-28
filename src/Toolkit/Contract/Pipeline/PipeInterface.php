<?php declare(strict_types=1);

namespace Salient\Contract\Pipeline;

use Closure;

/**
 * @template TInput
 * @template TOutput
 * @template TArgument
 */
interface PipeInterface
{
    /**
     * @param TInput|TOutput $payload
     * @param PipelineInterface<TInput,TOutput,TArgument> $pipeline
     * @param TArgument $arg
     * @return ($payload is TInput ? TInput|TOutput : TOutput)
     */
    public function __invoke($payload, Closure $next, PipelineInterface $pipeline, $arg);
}
