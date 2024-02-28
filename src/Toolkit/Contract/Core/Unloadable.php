<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * Has underlying resources to close
 *
 * @api
 */
interface Unloadable
{
    /**
     * Close the object's underlying resources
     */
    public function unload(): void;
}
