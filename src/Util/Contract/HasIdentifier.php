<?php declare(strict_types=1);

namespace Lkrms\Contract;

interface HasIdentifier
{
    /**
     * Get the object's unique identifier
     *
     * @return int|string|null
     */
    public function id();
}
