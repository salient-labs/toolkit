<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Can have parents and/or children of the same type
 *
 */
interface IHierarchy
{
    /**
     * Set or unset the object's parent
     *
     * Removes the object from its current parent (if it has one) and makes it a
     * child of a new parent (if `$parent` is not null).
     *
     * @param static|null $parent
     * @return $this
     */
    public function setParent($parent);
}
