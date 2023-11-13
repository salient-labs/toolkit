<?php declare(strict_types=1);

namespace Lkrms\Contract;

interface HasDescription extends HasName
{
    /**
     * Get a description of the object
     */
    public function description(): string;
}
