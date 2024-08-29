<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Event;

use Salient\Contract\Curler\CurlerInterface;

/**
 * @api
 */
interface CurlerEventInterface
{
    /**
     * Get the Curler instance that dispatched the event
     */
    public function getCurler(): CurlerInterface;
}
