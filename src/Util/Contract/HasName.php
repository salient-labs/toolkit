<?php declare(strict_types=1);

namespace Lkrms\Contract;

interface HasName
{
    /**
     * Get the name of the object
     */
    public function name(): string;
}
