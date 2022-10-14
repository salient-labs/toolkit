<?php

declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Creates an immutable version of the parent class
 *
 * The parent class must use {@see TMutable} and apply changes only to the
 * instance returned by {@see TMutable::getMutable()}.
 */
trait TImmutable
{
    final public static function fromMutable(parent $mutable): self
    {
        if ($mutable instanceof self)
        {
            return $mutable;
        }

        return $mutable->toImmutable();
    }

    /**
     * @return $this
     */
    final protected function getMutable()
    {
        return clone $this;
    }

}
