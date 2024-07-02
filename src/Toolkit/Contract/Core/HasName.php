<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
 */
interface HasName
{
    /**
     * Get the name of the object
     */
    public function getName(): string;
}
