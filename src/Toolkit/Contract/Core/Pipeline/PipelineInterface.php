<?php declare(strict_types=1);

namespace Salient\Contract\Core\Pipeline;

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
     * Call {@see EntityPipelineInterface::run()} to run the pipeline and get
     * the result.
     *
     * @template T0
     * @template T1
     *
     * @param T0 $payload
     * @param T1 $arg
     * @return EntityPipelineInterface<TInput&T0,TOutput,TArgument&T1>
     */
    public function send($payload, $arg = null);

    /**
     * Pass a list of payloads to the pipeline
     *
     * Call {@see StreamPipelineInterface::start()} to run the pipeline with
     * each value in `$payload` and get the results via a generator.
     *
     * @template T0
     * @template T1
     *
     * @param iterable<T0> $payload
     * @param T1 $arg
     * @return StreamPipelineInterface<TInput&T0,TOutput,TArgument&T1>
     */
    public function stream(iterable $payload, $arg = null);
}
