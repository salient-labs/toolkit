<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
 */
interface Hierarchical
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
     * - `$child->setParent(null)` is equivalent to
     *   `$parent->removeChild($child)`
     *
     * @param static|null $parent
     * @return static
     */
    public function setParent($parent);

    /**
     * Add a child to the object
     *
     * Equivalent to `$child->setParent($parent)`.
     *
     * @param static $child
     * @return static
     */
    public function addChild($child);

    /**
     * Remove a child from the object
     *
     * Equivalent to `$child->setParent(null)`.
     *
     * @param static $child
     * @return static
     */
    public function removeChild($child);

    /**
     * Get the length of the path to the object's root node
     */
    public function getDepth(): int;

    /**
     * Get the number of objects descended from the object
     */
    public function countDescendants(): int;
}
