<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\ArrayMapperFlag;

/**
 * Sends a payload through a series of pipes to a destination
 *
 * @template TInput
 * @template TOutput
 * @template TArgument
 */
interface IPipeline extends IImmutable
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
     * @param int $conformity One of the {@see ArrayKeyConformity} values. Use
     * `COMPLETE` wherever possible to improve performance.
     * @psalm-param ArrayKeyConformity::* $conformity
     * @return $this
     */
    public function withConformity(int $conformity = ArrayKeyConformity::PARTIAL);

    /**
     * Apply a callback to each payload before it is sent
     *
     * This method can only be called once per pipeline.
     *
     * @param callable $callback
     * ```php
     * fn($payload, IPipeline $pipeline, $arg)
     * ```
     * @psalm-param callable(TInput, IPipeline, TArgument): (TInput|TOutput) $callback
     * @return $this
     */
    public function after(callable $callback);

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
     * @param IPipe|callable|class-string<IPipe> ...$pipes Each pipe must be an
     * `IPipe` object, the name of an `IPipe` class to instantiate, or a closure
     * with the following signature:
     * ```php
     * function ($payload, Closure $next, IPipeline $pipeline, $arg)
     * ```
     * @psalm-param IPipe<TInput,TOutput,TArgument>|(callable(TInput|TOutput, \Closure, IPipeline, TArgument): (TInput|TOutput))|class-string<IPipe> ...$pipes
     * @return $this
     */
    public function through(...$pipes);

    /**
     * Add a simple callback to the pipeline
     *
     * @param callable $callback
     * ```php
     * fn($payload, IPipeline $pipeline, $arg)
     * ```
     * @psalm-param (callable(TInput, IPipeline, TArgument): (TInput|TOutput)) $callback
     * @return $this
     */
    public function throughCallback(callable $callback);

    /**
     * Add an array key mapper to the pipeline
     *
     * @param array<int|string,int|string|array<int,int|string>> $keyMap An
     * array that maps input keys to one or more output keys.
     * @param int $flags A bitmask of {@see ArrayMapperFlag} values.
     * @psalm-param int-mask-of<ArrayMapperFlag::*> $flags
     * @return $this
     */
    public function throughKeyMap(array $keyMap, int $flags = ArrayMapperFlag::ADD_UNMAPPED);

    /**
     * Apply a callback to each result
     *
     * This method can only be called once per pipeline.
     *
     * @template TThenOutput
     * @param callable $callback
     * ```php
     * fn($result, IPipeline $pipeline, $arg)
     * ```
     * @psalm-param callable(TInput|TOutput, IPipeline, TArgument): TThenOutput $callback
     * @return $this
     * @psalm-return IPipeline<TInput,TThenOutput,TArgument>
     */
    public function then(callable $callback);

    /**
     * Apply a filter to each result
     *
     * This method can only be called once per pipeline.
     *
     * Analogous to `array_filter()`. If `$filter` returns `true`, `$result` is
     * returned to the caller, otherwise:
     * - if {@see IPipeline::stream()} was called, the result is discarded
     * - if {@see IPipeline::send()} was called, an exception is thrown
     *
     * @param callable $filter
     * ```php
     * fn($result, IPipeline $pipeline, $arg): bool
     * ```
     * @psalm-param callable(TOutput, IPipeline, TArgument): bool $filter
     * @return $this
     */
    public function unless(callable $filter);

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
     * @return int One of the {@see ArrayKeyConformity} values.
     * @psalm-return ArrayKeyConformity::*
     * @see IPipeline::withConformity()
     */
    public function getConformity(): int;

    /**
     * Run the pipeline and pass the result to another pipeline
     *
     * @template TNextOutput
     * @psalm-param IPipeline<TOutput,TNextOutput,TArgument> $next
     * @return $this
     * @psalm-return IPipeline<TOutput,TNextOutput,TArgument>
     */
    public function runThrough(IPipeline $next);

    /**
     * Run the pipeline and pass each result to another pipeline
     *
     * {@see IPipeline::stream()} must be called before
     * {@see IPipeline::startThrough()} can be used to run the pipeline.
     *
     * @template TNextOutput
     * @psalm-param IPipeline<TOutput,TNextOutput,TArgument> $next
     * @return $this
     * @psalm-return IPipeline<TOutput,TNextOutput,TArgument>
     */
    public function startThrough(IPipeline $next);
}
