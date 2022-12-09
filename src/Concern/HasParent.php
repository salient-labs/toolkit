<?php declare(strict_types=1);

namespace Lkrms\Concern;

use UnexpectedValueException;

/**
 * Implements IHierarchy
 *
 * @see \Lkrms\Contract\IHierarchy
 */
trait HasParent
{
    abstract private static function getParentProperty(): string;

    abstract private static function getChildrenProperty(): string;

    /**
     * @var string[]|null
     */
    private static $_HierarchyProperties;

    private static function getHierarchyProperties()
    {
        if (is_null(self::$_HierarchyProperties)) {
            // Subclasses can't override these properties, hence `self::`
            // instead of `static::`
            return self::$_HierarchyProperties = [self::getParentProperty(), self::getChildrenProperty()];
        }

        return self::$_HierarchyProperties;
    }

    /**
     * @param static|null $parent
     * @return $this
     */
    final public function setParent($parent)
    {
        [$_parent, $_children] = self::getHierarchyProperties();

        if ($parent === $this->{$_parent} &&
                (is_null($parent) || in_array($this, $parent->{$_children} ?: [], true))) {
            return $this;
        }

        if (!is_null($this->{$_parent})) {
            $this->{$_parent}->{$_children} = array_values(array_filter(
                $this->{$_parent}->{$_children},
                fn($child) => $child !== $this
            ));
        }

        $this->{$_parent} = $parent;

        if (!is_null($parent)) {
            $this->{$_parent}->{$_children}[] = $this;
        }

        return $this;
    }

    /**
     * @param static $child
     * @return $this
     */
    final public function removeChild($child)
    {
        [$_parent] = self::getHierarchyProperties();

        if ($child->{$_parent} !== $this) {
            throw new UnexpectedValueException("\$child->{$_parent} is not \$this");
        }

        $child->setParent(null);

        return $this;
    }

    final public function getDepth(): int
    {
        [$_parent] = self::getHierarchyProperties();

        $depth  = 0;
        $parent = $this->{$_parent};
        while (!is_null($parent)) {
            $depth++;
            $parent = $parent->{$_parent};
        }

        return $depth;
    }
}
