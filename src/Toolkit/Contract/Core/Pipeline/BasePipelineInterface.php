<?php declare(strict_types=1);

namespace Salient\Contract\Core\Pipeline;

use Salient\Contract\Catalog\HasConformity;
use Salient\Contract\Core\Chainable;
use Salient\Contract\Core\Immutable;
use Closure;

/**
 * @api
 *
 * @template TInput
 * @template TOutput
 * @template TArgument
 */
interface BasePipelineInterface extends Chainable, Immutable, HasConformity
{
    /**
     * Apply a closure to each payload before it is sent
     *
     * This method can only be called once per pipeline.
     *
     * @param Closure(TInput $payload, static $pipeline, TArgument $arg): (TInput|TOutput) $closure
     * @return static
     */
    public function after(Closure $closure);

    /**
     * Apply a closure to each payload before it is sent if after() hasn't
     * already been called
     *
     * @param Closure(TInput $payload, static $pipeline, TArgument $arg): (TInput|TOutput) $closure
     * @return static
     */
    public function afterIf(Closure $closure);

    /**
     * Add a pipe to the pipeline
     *
     * A pipe should do something with the `$payload` it receives before taking
     * one of the following actions:
     *
     * - return the value of `$next($payload)`
     * - return a value that will be discarded by
     *   {@see StreamPipelineInterface::unless()}, bypassing any remaining pipes
     *   and {@see BasePipelineInterface::then()}, if applicable
     * - throw an exception
     *
     * @param (Closure(TInput $payload, Closure $next, static $pipeline, TArgument $arg): (TInput|TOutput))|(Closure(TOutput $payload, Closure $next, static $pipeline, TArgument $arg): TOutput) $pipe
     * @return static
     */
    public function through(Closure $pipe);

    /**
     * Add a simple closure to the pipeline
     *
     * @param (Closure(TInput $payload, static $pipeline, TArgument $arg): (TInput|TOutput))|(Closure(TOutput $payload, static $pipeline, TArgument $arg): TOutput) $closure
     * @return static
     */
    public function throughClosure(Closure $closure);

    /**
     * Add an array key mapper to the pipeline
     *
     * @param array<array-key,array-key|array-key[]> $keyMap An array that maps
     * input keys to one or more output keys.
     * @param int-mask-of<ArrayMapperInterface::REMOVE_NULL|ArrayMapperInterface::ADD_UNMAPPED|ArrayMapperInterface::ADD_MISSING|ArrayMapperInterface::REQUIRE_MAPPED> $flags
     * @return static
     */
    public function throughKeyMap(array $keyMap, int $flags = ArrayMapperInterface::ADD_UNMAPPED);

    /**
     * Apply a closure to each result
     *
     * This method can only be called once per pipeline, and only if
     * {@see StreamPipelineInterface::collectThen()} is not also called.
     *
     * @param (Closure(TInput $result, static $pipeline, TArgument $arg): TOutput)|(Closure(TOutput $result, static $pipeline, TArgument $arg): TOutput) $closure
     * @return static
     */
    public function then(Closure $closure);

    /**
     * Apply a closure to each result if then() hasn't already been called
     *
     * @param (Closure(TInput $result, static $pipeline, TArgument $arg): TOutput)|(Closure(TOutput $result, static $pipeline, TArgument $arg): TOutput) $closure
     * @return static
     */
    public function thenIf(Closure $closure);

    /**
     * Pass each result to a closure
     *
     * Results not discarded by {@see StreamPipelineInterface::unless()} are
     * passed to the closure before leaving the pipeline. The closure's return
     * value is ignored.
     *
     * @param Closure(TOutput $result, static $pipeline, TArgument $arg): mixed $closure
     * @return static
     */
    public function cc(Closure $closure);
}
