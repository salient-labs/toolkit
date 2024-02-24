<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * @api
 */
interface Describable
{
    /**
     * Get a description of the object
     */
    public function description(): string;
}
