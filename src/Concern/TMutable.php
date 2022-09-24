<?php

declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Allows an immutable subclass to be created
 *
 * Exhibiting classes must apply changes only to the instance returned by
 * {@see TMutable::getMutable()}. For mutable classes, the instance returned
 * will be `$this`; for immutable classes that use {@see TImmutable}, it will be
 * a clone of `$this`.
 */
trait TMutable
{
    abstract protected function toImmutable();

    /**
     * @return $this
     */
    protected function getMutable()
    {
        return $this;
    }

}
