<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Event;

use CurlHandle;

/**
 * @api
 */
interface CurlEvent extends CurlerEvent
{
    /**
     * Get the cURL handle associated with the event
     *
     * @return CurlHandle|resource
     */
    public function getCurlHandle();
}
