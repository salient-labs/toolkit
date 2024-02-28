<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * @template TInput
 * @template TOutput
 * @template TArgument
 *
 * @extends BasePipelineInterface<TInput,TOutput,TArgument>
 */
interface PipelineInterface extends BasePipelineInterface
{
    /**
     * Pass a payload to the pipeline
     *
     * @param TInput $payload
     * @param TArgument $arg
     * @return EntityPipelineInterface<TInput,TOutput,TArgument>
     */
    public function send($payload, $arg = null);

    /**
     * Pass a list of payloads to the pipeline
     *
     * Call {@see PipelineInterface::start()} to run the pipeline with each
     * value in `$payload` and get the results via a generator.
     *
     * @param iterable<TInput> $payload
     * @param TArgument $arg
     * @return StreamPipelineInterface<TInput,TOutput,TArgument>
     */
    public function stream(iterable $payload, $arg = null);
}
