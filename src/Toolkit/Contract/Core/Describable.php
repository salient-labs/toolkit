<?php declare(strict_types=1);

namespace Salient\Contract\Core;

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
