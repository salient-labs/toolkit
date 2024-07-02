<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
 */
interface HasDescription
{
    /**
     * Get a description of the object
     */
    public function getDescription(): string;
}
