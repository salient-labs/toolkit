<?php declare(strict_types=1);

namespace Salient\Contract\Core\Pipeline;

use Salient\Contract\Catalog\ListConformity;

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
     * @param ListConformity::* $conformity Use {@see ListConformity::COMPLETE}
     * wherever possible to improve performance.
     * @return static
     */
    public function withConformity($conformity);

    /**
     * Get the payload's conformity level
     *
     * @return ListConformity::*
     */
    public function getConformity();
}
