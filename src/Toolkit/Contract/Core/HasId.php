<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
 */
interface HasId
{
    /**
     * Get the unique identifier of the object
     *
     * @return int|string|null
     */
    public function getId();
}
