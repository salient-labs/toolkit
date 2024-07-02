<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
 */
interface HasId
{
    /**
     * Get the object's unique identifier
     *
     * @return int|string|null
     */
    public function getId();
}
