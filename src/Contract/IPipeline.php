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
     * @return $this
     */
    public function send($payload);

    /**
     * Provide a payload source
     *
     * Call {@see IPipeline::start()} to run the pipeline with each value in
     * `$payload` and `yield` the results via a generator.
     *
     * @param iterable $payload Must be traversable with `foreach`.
     * @return $this
     */
    public function stream(iterable $payload);

    /**
     * Add pipes to the pipeline
     *
     * A pipe must be one of the following:
     * - an instance of a class that implements {@see IPipe}
     * - the name of a class that implements {@see IPipe} (an instance will be
     *   created), or
     * - a callback with the same signature as {@see IPipe::handle()}:
     * ```php
     * function ($payload, Closure $next)
     * ```
     *
     * Whichever form it takes, a pipe should perform an action that uses,
     * changes and/or replaces `$payload`, then either:
     * - return the value of `$next($payload)`, or
     * - throw an exception
     *
     * @param IPipe|callable|string ...$pipes
     * @return $this
     */
    public function through(...$pipes);

    /**
     * Add a pipe to the pipeline
     *
     * See {@see IPipeline::through()} for more information.
     *
     * @param IPipe|callable|string $pipe
     * @return $this
     */
    public function pipe($pipe);

    /**
     * Add a simple callback to the pipeline
     *
     * @return $this
     */
    public function apply(callable $callback);

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
    public function map(array $keyMap, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED);

    /**
     * Set a callback that will be applied to each result
     *
     * This method can only be called once per pipeline.
     *
     * @return $this
     */
    public function then(callable $callback);

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
