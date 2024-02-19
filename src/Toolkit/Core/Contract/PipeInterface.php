<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Salient\Core\Contract\PipelineInterface;
use Closure;

/**
 * Forms part of a pipeline
 *
 * @template TInput
 * @template TOutput
 * @template TArgument
 *
 * @see PipelineInterface
 */
interface PipeInterface
{
    /**
     * @param TInput|TOutput $payload
     * @param PipelineInterface<TInput,TOutput,TArgument> $pipeline
     * @param TArgument $arg
     * @return TInput|TOutput
     */
    public function handle($payload, Closure $next, PipelineInterface $pipeline, $arg);
}
