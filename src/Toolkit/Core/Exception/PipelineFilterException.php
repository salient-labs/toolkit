<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Lkrms\Contract\IPipeline;
use Salient\Core\AbstractException;

/**
 * Thrown when the result of a pipeline run is rejected by a filter
 *
 * @see IPipeline::unless()
 * @see IPipeline::unlessIf()
 */
class PipelineFilterException extends AbstractException
{
    /**
     * @var mixed
     */
    protected $Payload;

    /**
     * @var mixed
     */
    protected $Result;

    /**
     * @param mixed $payload
     * @param mixed $result
     */
    public function __construct($payload = null, $result = null)
    {
        $this->Payload = $payload;
        $this->Result = $result;

        parent::__construct('Result rejected by filter');
    }
}
