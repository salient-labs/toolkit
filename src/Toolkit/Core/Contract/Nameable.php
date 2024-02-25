<?php declare(strict_types=1);

namespace Salient\Core\Contract;

interface Nameable
{
    /**
     * Get the name of the object
     */
    public function name(): string;
}
