<?php declare(strict_types=1);

namespace Lkrms\Contract;

interface Unloadable
{
    /**
     * Close the object's underlying resources
     */
    public function unload(): void;
}
