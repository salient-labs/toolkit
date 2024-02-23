<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Salient\Core\Catalog\ArrayMapperFlag;
use Closure;

/**
 * @template TInput
 * @template TOutput
 * @template TArgument
 */
interface BasePipelineInterface extends Chainable, Immutable
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
     * Add pipes to the pipeline
     *
     * A pipe must be one of the following:
     *
     * - an instance of a class that implements {@see PipeInterface}
     * - the name of a class that implements {@see PipeInterface}
     * - a closure
     *
     * Whichever form it takes, a pipe should do something with `$payload`
     * before taking one of the following actions:
     *
     * - return the value of `$next($payload)`
     * - return a value that will be discarded by
     *   {@see PipelineInterface::unless()}, bypassing any remaining pipes and
     *   {@see PipelineInterface::then()}, if applicable
     * - throw an exception
     *
     * @param (Closure(TInput|TOutput $payload, Closure $next, static $pipeline, TArgument $arg): (TInput|TOutput))|PipeInterface<TInput,TOutput,TArgument>|class-string<PipeInterface<TInput,TOutput,TArgument>> ...$pipes
     * @return static
     */
    public function through(...$pipes);

    /**
     * Add a simple closure to the pipeline
     *
     * @param Closure(TInput|TOutput $payload, static $pipeline, TArgument $arg): (TInput|TOutput) $closure
     * @return static
     */
    public function throughClosure(Closure $closure);

    /**
     * Add an array key mapper to the pipeline
     *
     * @param array<array-key,array-key|array-key[]> $keyMap An array that maps
     * input keys to one or more output keys.
     * @param int-mask-of<ArrayMapperFlag::*> $flags
     * @return static
     */
    public function throughKeyMap(array $keyMap, int $flags = ArrayMapperFlag::ADD_UNMAPPED);

    /**
     * Apply a closure to each result
     *
     * This method can only be called once per pipeline, and only if
     * {@see StreamPipelineInterface::collectThen()} is not also called.
     *
     * @param Closure(TInput|TOutput $result, static $pipeline, TArgument $arg): TOutput $closure
     * @return static
     */
    public function then(Closure $closure);

    /**
     * Apply a closure to each result if then() hasn't already been called
     *
     * @param Closure(TInput|TOutput $result, static $pipeline, TArgument $arg): TOutput $closure
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
