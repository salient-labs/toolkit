<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\ArrayKeyConformity;

/**
 * Sends a payload through a series of pipes to a destination
 *
 */
interface IPipeline
{
    /**
     * Set the payload
     *
     * If values in `$payload` can be traversed with `foreach` and the pipeline
     * is terminated with {@see IPipeline::thenStream()}, each value will be
     * sent through the pipeline as an individual payload, and results will be
     * returned via a forward-only iterator.
     *
     * @return $this
     */
    public function send($payload);

    /**
     * Set the pipes in the pipeline, removing any existing pipes
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
     * Whichever form it takes, a pipe:
     * - SHOULD use `$payload` to perform an action
     * - MUST either:
     *   - return the value of `$next($payload)`, or
     *   - throw an exception
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
    public function map(array $keyMap, int $conformity = ArrayKeyConformity::NONE, int $flags = 0);

    /**
     * Run the pipeline and pass the result to a callback
     *
     * Returns the callback's return value
     *
     */
    public function then(callable $callback);

    /**
     * Run the pipeline and return the result
     *
     */
    public function thenReturn();

    /**
     * Run the pipeline and yield the result for each of the payload's values as
     * they are read by the caller
     *
     */
    public function thenStream(): iterable;

}
