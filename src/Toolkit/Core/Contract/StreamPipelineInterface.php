<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Closure;

/**
 * @template TInput
 * @template TOutput
 * @template TArgument
 *
 * @extends PayloadPipelineInterface<TInput,TOutput,TArgument>
 */
interface StreamPipelineInterface extends PayloadPipelineInterface
{
    /**
     * Pass results to a closure in batches
     *
     * {@see PipelineInterface::collectThen()} can only be called once per
     * pipeline, and only if {@see PipelineInterface::then()} is not also
     * called.
     *
     * Values returned by the closure are returned to the caller via a
     * forward-only iterator.
     *
     * @param Closure(array<TInput|TOutput> $results, static $pipeline, TArgument $arg): iterable<TOutput> $closure
     * @return static
     */
    public function collectThen(Closure $closure);

    /**
     * Pass results to a closure in batches if collectThen() hasn't already been
     * called
     *
     * @param Closure(array<TInput|TOutput> $results, static $pipeline, TArgument $arg): iterable<TOutput> $closure
     * @return static
     */
    public function collectThenIf(Closure $closure);

    /**
     * Apply a filter to each result
     *
     * {@see PipelineInterface::unless()} can only be called once per pipeline.
     *
     * If `$filter` returns `false`, `$result` is returned to the caller,
     * otherwise it is discarded.
     *
     * @param Closure(TOutput|null $result, static $pipeline, TArgument $arg): bool $filter
     * @return static
     */
    public function unless(Closure $filter);

    /**
     * Apply a filter to each result if unless() hasn't already been called
     *
     * @param Closure(TOutput|null $result, static $pipeline, TArgument $arg): bool $filter
     * @return static
     */
    public function unlessIf(Closure $filter);

    /**
     * Run the pipeline with each of the payload's values and return the results
     * via a forward-only iterator
     *
     * @return iterable<TOutput>
     */
    public function start(): iterable;

    /**
     * Run the pipeline and pass each result to another pipeline
     *
     * @template TNextOutput
     *
     * @param PipelineInterface<TOutput,TNextOutput,TArgument> $next
     * @return StreamPipelineInterface<TOutput,TNextOutput,TArgument>
     */
    public function startInto(PipelineInterface $next);
}
