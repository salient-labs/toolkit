<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Core\Contract\Treeable;
use Salient\Core\Introspector;
use LogicException;

/**
 * Implements Treeable
 *
 * @see Treeable
 */
trait TreeableTrait
{
    abstract public static function getParentProperty(): string;

    abstract public static function getChildrenProperty(): string;

    /**
     * @var array<class-string<self>,string>
     */
    private static $ParentProperties = [];

    /**
     * @var array<class-string<self>,string>
     */
    private static $ChildrenProperties = [];

    private static function loadHierarchyProperties(): void
    {
        $introspector = Introspector::get(static::class);

        if (!$introspector->IsTreeable) {
            throw new LogicException(
                sprintf(
                    '%s does not implement %s or does not return valid parent/child properties',
                    static::class,
                    Treeable::class,
                )
            );
        }

        self::$ParentProperties[static::class] =
            $introspector->Properties[$introspector->ParentProperty]
                ?? $introspector->ParentProperty;
        self::$ChildrenProperties[static::class] =
            $introspector->Properties[$introspector->ChildrenProperty]
                ?? $introspector->ChildrenProperty;
    }

    /**
     * @return static|null
     */
    final public function getParent()
    {
        if (!isset(self::$ParentProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_parent = self::$ParentProperties[static::class];

        return $this->{$_parent};
    }

    /**
     * @return static[]
     */
    final public function getChildren(): array
    {
        if (!isset(self::$ChildrenProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_children = self::$ChildrenProperties[static::class];

        return $this->{$_children} ?? [];
    }

    /**
     * @param (Treeable&static)|null $parent
     * @return $this
     */
    final public function setParent($parent)
    {
        if (!isset(self::$ParentProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_parent = self::$ParentProperties[static::class];
        $_children = self::$ChildrenProperties[static::class];

        if ($parent === $this->{$_parent} &&
            ($parent === null ||
                in_array($this, $parent->{$_children} ?: [], true))) {
            return $this;
        }

        // Remove the object from its current parent
        if ($this->{$_parent} !== null) {
            $this->{$_parent}->{$_children} =
                array_values(
                    array_filter(
                        $this->{$_parent}->{$_children},
                        fn($child) => $child !== $this
                    )
                );
        }

        $this->{$_parent} = $parent;

        if ($parent !== null) {
            return $this->{$_parent}->{$_children}[] = $this;
        }

        return $this;
    }

    /**
     * @param static $child
     * @return $this
     */
    final public function addChild($child)
    {
        return $child->setParent($this);
    }

    /**
     * @param static $child
     * @return $this
     */
    final public function removeChild($child)
    {
        if (!isset(self::$ParentProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_parent = self::$ParentProperties[static::class];

        if ($child->{$_parent} !== $this) {
            throw new LogicException('Argument #1 ($child) is not a child of this object');
        }

        return $child->setParent(null);
    }

    final public function getDepth(): int
    {
        if (!isset(self::$ParentProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_parent = self::$ParentProperties[static::class];

        $depth = 0;
        $parent = $this->{$_parent};
        while ($parent !== null) {
            $depth++;
            $parent = $parent->{$_parent};
        }

        return $depth;
    }

    final public function getDescendantCount(): int
    {
        if (!isset(self::$ChildrenProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        return $this->countDescendants(
            self::$ChildrenProperties[static::class]
        );
    }

    private function countDescendants(string $_children): int
    {
        /** @var static[] */
        $children = $this->{$_children} ?? [];

        if ($children === []) {
            return 0;
        }

        $count = 0;
        foreach ($children as $child) {
            $count += 1 + $child->countDescendants($_children);
        }

        return $count;
    }
}
