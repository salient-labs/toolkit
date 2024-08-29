<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Event;

use CurlHandle;

/**
 * @api
 */
interface CurlEventInterface extends CurlerEventInterface
{
    /**
     * Get the cURL handle associated with the event
     *
     * @return CurlHandle|resource
     */
    public function getCurlHandle();
}
