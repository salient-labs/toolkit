<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Lkrms\Support\Catalog\ArrayKeyConformity;

/**
 * @template TInput
 * @template TOutput
 * @template TArgument
 *
 * @extends BasePipelineInterface<TInput,TOutput,TArgument>
 */
interface PayloadPipelineInterface extends BasePipelineInterface
{
    /**
     * Set the payload's array key conformity
     *
     * `$conformity` is passed to any array key mappers added to the pipeline
     * with {@see PipelineInterface::throughKeyMap()}. It has no effect
     * otherwise.
     *
     * @param ArrayKeyConformity::* $conformity Use
     * {@see ArrayKeyConformity::COMPLETE} wherever possible to improve
     * performance.
     * @return static
     */
    public function withConformity($conformity);

    /**
     * Get the payload's array key conformity
     *
     * @return ArrayKeyConformity::*
     */
    public function getConformity();
}
