<?php declare(strict_types=1);

namespace Salient\Contract\Core;

interface Identifiable
{
    /**
     * Get the object's unique identifier
     *
     * @return int|string|null
     */
    public function id();
}
