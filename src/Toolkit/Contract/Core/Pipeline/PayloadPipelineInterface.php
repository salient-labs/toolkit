<?php declare(strict_types=1);

namespace Salient\Contract\Core\Pipeline;

/**
 * @api
 *
 * @template TInput
 * @template TOutput
 * @template TArgument
 *
 * @extends BasePipelineInterface<TInput,TOutput,TArgument>
 */
interface PayloadPipelineInterface extends BasePipelineInterface
{
    /**
     * Set the payload's conformity level
     *
     * `$conformity` is passed to any array key mappers added to the pipeline
     * with {@see BasePipelineInterface::throughKeyMap()}. It has no effect
     * otherwise.
     *
     * @param BasePipelineInterface::* $conformity Use
     * {@see BasePipelineInterface::CONFORMITY_COMPLETE} wherever possible to
     * improve performance.
     * @return static
     */
    public function withConformity(int $conformity);

    /**
     * Get the payload's conformity level
     *
     * @return BasePipelineInterface::*
     */
    public function getConformity(): int;
}
