<?php declare(strict_types=1);

namespace Salient\Contract\Core\Pipeline;

/**
 * @api
 *
 * @template TInput
 * @template TOutput
 * @template TArgument
 *
 * @extends PayloadPipelineInterface<TInput,TOutput,TArgument>
 */
interface EntityPipelineInterface extends PayloadPipelineInterface
{
    /**
     * Run the pipeline and return the result
     *
     * @return TOutput
     */
    public function run();

    /**
     * Run the pipeline and pass the result to another pipeline
     *
     * @template TNextOutput
     *
     * @param PipelineInterface<TOutput,TNextOutput,TArgument> $next
     * @return EntityPipelineInterface<TOutput,TNextOutput,TArgument>
     */
    public function runInto(PipelineInterface $next);
}
