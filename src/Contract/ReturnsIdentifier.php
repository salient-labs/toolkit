<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Instances have a unique identifier
 */
interface ReturnsIdentifier
{
    /**
     * Get the object's unique identifier
     *
     * @return int|string|null
     */
    public function id();
}
