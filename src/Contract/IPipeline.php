<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\ArrayMapperFlag;

/**
 * Sends a payload through a series of pipes to a destination
 *
 * @template TInput
 * @template TOutput
 * @template TArgument
 */
interface IPipeline extends IFluentInterface, IImmutable
{
    /**
     * Set the payload
     *
     * @param TInput $payload
     * @param TArgument $arg Passed to each pipe.
     * @return $this
     */
    public function send($payload, $arg = null);

    /**
     * Provide a payload source
     *
     * Call {@see IPipeline::start()} to run the pipeline with each value in
     * `$payload` and `yield` the results via a generator.
     *
     * If payloads are associative arrays, call
     * {@see IPipeline::withConformity()} to improve performance.
     *
     * @param iterable<TInput> $payload Must be traversable with `foreach`.
     * @param TArgument $arg Passed to each pipe.
     * @return $this
     */
    public function stream(iterable $payload, $arg = null);

    /**
     * Specify the payload's array key conformity
     *
     * `$conformity` is passed to any array key mappers added to the pipeline
     * with {@see IPipeline::throughKeyMap()}. It has no effect otherwise.
     *
     * Calls to {@see IPipeline::send()} and {@see IPipeline::stream()} reset
     * the pipeline to conformity level {@see ArrayKeyConformity::NONE}, so this
     * method must be called **after** providing the payload to which it
     * applies.
     *
     * @param ArrayKeyConformity::* $conformity Use
     * {@see ArrayKeyConformity::COMPLETE} wherever possible to improve
     * performance.
     * @return $this
     */
    public function withConformity($conformity = ArrayKeyConformity::PARTIAL);

    /**
     * Apply a callback to each payload before it is sent
     *
     * This method can only be called once per pipeline.
     *
     * @param callable(TInput $payload, IPipeline<TInput,TOutput,TArgument> $pipeline, TArgument $arg): (TInput|TOutput) $callback
     * @return $this
     */
    public function after(callable $callback);

    /**
     * Apply a callback to each payload before it is sent if an after() callback
     * hasn't already been applied
     *
     * @param callable(TInput $payload, IPipeline<TInput,TOutput,TArgument> $pipeline, TArgument $arg): (TInput|TOutput) $callback
     * @return $this
     */
    public function afterIf(callable $callback);

    /**
     * Add pipes to the pipeline
     *
     * A pipe must be one of the following:
     * - an instance of a class that implements {@see IPipe}
     * - the name of a class that implements {@see IPipe} (an instance will be
     *   created), or
     * - a callback with the same signature as {@see IPipe::handle()}:
     * ```php
     * function ($payload, Closure $next, IPipeline $pipeline, $arg)
     * ```
     *
     * Whichever form it takes, a pipe should use, mutate and/or replace
     * `$payload`, then either:
     * - return the value of `$next($payload)`,
     * - throw an exception, or
     * - return a value the {@see IPipeline::unless()} callback will discard
     *   (this bypasses any remaining pipes and the callback passed to
     *   {@see IPipeline::then()}, if applicable)
     *
     * @param IPipe<TInput,TOutput,TArgument>|(callable(TInput|TOutput $payload, \Closure $next, IPipeline<TInput,TOutput,TArgument> $pipeline, TArgument $arg): (TInput|TOutput))|class-string<IPipe<TInput,TOutput,TArgument>> ...$pipes
     * Each pipe must be an `IPipe` object, the name of an `IPipe` class to
     * instantiate, or a closure.
     * @return $this
     */
    public function through(...$pipes);

    /**
     * Add a simple callback to the pipeline
     *
     * @param (callable(TInput $payload, IPipeline<TInput,TOutput,TArgument> $pipeline, TArgument $arg): (TInput|TOutput)) $callback
     * @return $this
     */
    public function throughCallback(callable $callback);

    /**
     * Add an array key mapper to the pipeline
     *
     * @param array<int|string,int|string|array<int,int|string>> $keyMap An
     * array that maps input keys to one or more output keys.
     * @param int-mask-of<ArrayMapperFlag::*> $flags
     * @return $this
     */
    public function throughKeyMap(array $keyMap, int $flags = ArrayMapperFlag::ADD_UNMAPPED);

    /**
     * Apply a callback to each result
     *
     * This method can only be called once per pipeline, and only if
     * {@see IPipeline::collectThen()} is not also called.
     *
     * @template TThenOutput
     * @param callable(TInput|TOutput $result, IPipeline<TInput,TOutput,TArgument> $pipeline, TArgument $arg): TThenOutput $callback
     * @return IPipeline<TInput,TThenOutput,TArgument>
     */
    public function then(callable $callback);

    /**
     * Apply a callback to each result if a then() callback hasn't already been
     * applied
     *
     * @template TThenOutput
     * @param callable(TInput|TOutput $result, IPipeline<TInput,TOutput,TArgument> $pipeline, TArgument $arg): TThenOutput $callback
     * @return IPipeline<TInput,TThenOutput,TArgument>
     */
    public function thenIf(callable $callback);

    /**
     * Collect results from the pipeline and pass them to a callback in batches
     *
     * {@see IPipeline::stream()} and {@see IPipeline::start()} must be used
     * with this method. It cannot be combined with {@see IPipeline::send()} and
     * {@see IPipeline::run()}.
     *
     * {@see IPipeline::collectThen()} can only be called once per pipeline, and
     * only if {@see IPipeline::then()} is not also called.
     *
     * Values returned by the callback are returned to the caller via a
     * forward-only iterator.
     *
     * @template TThenOutput
     * @param callable(array<TInput|TOutput> $results, IPipeline<TInput,TOutput,TArgument> $pipeline, TArgument $arg): iterable<TThenOutput> $callback
     * @return IPipeline<TInput,TThenOutput,TArgument>
     */
    public function collectThen(callable $callback);

    /**
     * Apply a filter to each result
     *
     * This method can only be called once per pipeline.
     *
     * Analogous to `array_filter()`, although the effect of the callback's
     * return value is inverted.
     *
     * If `$filter` returns `false`, `$result` is returned to the caller,
     * otherwise:
     * - if {@see IPipeline::stream()} was called, the result is discarded
     * - if {@see IPipeline::send()} was called, an exception is thrown
     *
     * @param callable(TOutput|null $result, IPipeline<TInput,TOutput,TArgument> $pipeline, TArgument $arg): bool $filter
     * @return $this
     */
    public function unless(callable $filter);

    /**
     * Apply a filter to each result if an unless() callback hasn't already been
     * applied
     *
     * See {@see IPipeline::unless()} for more information.
     *
     * @param callable(TOutput|null $result, IPipeline<TInput,TOutput,TArgument> $pipeline, TArgument $arg): bool $filter
     * @return $this
     */
    public function unlessIf(callable $filter);

    /**
     * Run the pipeline and return the result
     *
     * @return TOutput
     */
    public function run();

    /**
     * Run the pipeline with each of the payload's values and return the results
     * via a forward-only iterator
     *
     * {@see IPipeline::stream()} must be called before
     * {@see IPipeline::start()} can be used to run the pipeline.
     *
     * @return iterable<TOutput>
     */
    public function start(): iterable;

    /**
     * Get the payload's array key conformity
     *
     * @return ArrayKeyConformity::*
     * @see IPipeline::withConformity()
     */
    public function getConformity();

    /**
     * Run the pipeline and pass the result to another pipeline
     *
     * @template TNextOutput
     * @param IPipeline<TOutput,TNextOutput,TArgument> $next
     * @return IPipeline<TOutput,TNextOutput,TArgument>
     */
    public function runInto(IPipeline $next);

    /**
     * Run the pipeline and pass each result to another pipeline
     *
     * {@see IPipeline::stream()} must be called before
     * {@see IPipeline::startInto()} can be used to run the pipeline.
     *
     * @template TNextOutput
     * @param IPipeline<TOutput,TNextOutput,TArgument> $next
     * @return IPipeline<TOutput,TNextOutput,TArgument>
     */
    public function startInto(IPipeline $next);
}
