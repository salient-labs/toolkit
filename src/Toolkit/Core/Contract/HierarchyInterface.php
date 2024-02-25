<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * @api
 */
interface HierarchyInterface
{
    /**
     * Get the parent of the object
     *
     * @return static|null
     */
    public function getParent();

    /**
     * Get the children of the object
     *
     * @return static[]
     */
    public function getChildren(): array;

    /**
     * Set or unset the parent of the object
     *
     * - `$child->setParent($parent)` is equivalent to
     *   `$parent->addChild($child)`
     * - `$child->setParent(null)` has the same effect as
     *   `$parent->removeChild($child)`
     *
     * @param static|null $parent
     * @return $this
     */
    public function setParent($parent);

    /**
     * Add a child to the object
     *
     * Equivalent to `$child->setParent($parent)`.
     *
     * @param static $child
     * @return $this
     */
    public function addChild($child);

    /**
     * Remove a child from the object
     *
     * Equivalent to `$child->setParent(null)`.
     *
     * @param static $child
     * @return $this
     */
    public function removeChild($child);

    /**
     * Get the object's distance from the top of the hierarchy it belongs to
     *
     * Returns `0` if the object has no parent, `1` if its parent has no parent,
     * and so on.
     */
    public function getDepth(): int;

    /**
     * Get the number of objects descended from the object
     */
    public function getDescendantCount(): int;
}
