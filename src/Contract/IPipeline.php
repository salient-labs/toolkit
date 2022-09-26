<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\ArrayMapperFlag;

/**
 * Sends a payload through a series of pipes to a destination
 *
 */
interface IPipeline
{
    /**
     * Set the payload
     *
     * Arguments added after `$payload` will be passed to each pipe.
     *
     * @return $this
     */
    public function send($payload, ...$args);

    /**
     * Provide a payload source
     *
     * Call {@see IPipeline::start()} to run the pipeline with each value in
     * `$payload` and `yield` the results via a generator.
     *
     * Arguments added after `$payload` will be passed to each pipe.
     *
     * @param iterable $payload Must be traversable with `foreach`.
     * @return $this
     */
    public function stream(iterable $payload, ...$args);

    /**
     * Set a callback that will be applied to each payload before it is sent
     *
     * This method can only be called once per pipeline.
     *
     * Arguments added after `$callback` will be passed to the callback
     * **before** any arguments added after `$payload` in
     * {@see IPipeline::send()} or {@see IPipeline::stream()}.
     *
     * @return $this
     */
    public function after(callable $callback, ...$args);

    /**
     * Add pipes to the pipeline
     *
     * A pipe must be one of the following:
     * - an instance of a class that implements {@see IPipe}
     * - the name of a class that implements {@see IPipe} (an instance will be
     *   created), or
     * - a callback with the same signature as {@see IPipe::handle()}:
     * ```php
     * function ($payload, Closure $next, ...$args)
     * ```
     *
     * Whichever form it takes, a pipe should perform an action that uses,
     * changes and/or replaces `$payload`, then either:
     * - return the value of `$next($payload)`, or
     * - throw an exception
     *
     * @param IPipe|callable|string ...$pipes Each pipe must be an `IPipe`
     * object, the name of an `IPipe` class to instantiate, or a closure with
     * the following signature:
     * ```php
     * function ($payload, Closure $next, ...$args)
     * ```
     * @return $this
     */
    public function through(...$pipes);

    /**
     * Add a simple callback to the pipeline
     *
     * @return $this
     */
    public function throughCallback(callable $callback, bool $suppressArgs = false);

    /**
     * Add an array key mapper to the pipeline
     *
     * @param array<int|string,int|string|array<int,int|string>> $keyMap An
     * array that maps input keys to one or more output keys.
     * @param int $conformity One of the {@see ArrayKeyConformity} values. Use
     * `COMPLETE` wherever possible to improve performance.
     * @param int $flags A bitmask of {@see \Lkrms\Support\ArrayMapperFlag}
     * values.
     *
     * @return $this
     */
    public function throughKeyMap(array $keyMap, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED);

    /**
     * Set a callback that will be applied to each result
     *
     * This method can only be called once per pipeline.
     *
     * Arguments added after `$callback` will be passed to the callback
     * **before** any arguments added after `$payload` in
     * {@see IPipeline::send()} or {@see IPipeline::stream()}.
     *
     * @return $this
     */
    public function then(callable $callback, ...$args);

    /**
     * Run the pipeline and return the result
     *
     */
    public function run();

    /**
     * Run the pipeline with each of the payload's values and return the results
     * via a forward-only iterator
     *
     * {@see IPipeline::stream()} must be called before
     * {@see IPipeline::start()} can be used to run the pipeline.
     */
    public function start(): iterable;

}
