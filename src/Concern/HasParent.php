<?php declare(strict_types=1);

namespace Lkrms\Concern;

use LogicException;

/**
 * Implements IHierarchy
 *
 * @see \Lkrms\Contract\IHierarchy
 */
trait HasParent
{
    /**
     * Get the name of the object's declared or magic parent property
     *
     */
    abstract protected static function getParentProperty(): string;

    /**
     * Get the name of the object's declared or magic child array property
     *
     */
    abstract protected static function getChildrenProperty(): string;

    /**
     * @var array<class-string<self>,string>
     */
    private static $_ParentProperties = [];

    /**
     * @var array<class-string<self>,string>
     */
    private static $_ChildrenProperties = [];

    private static function loadHierarchyProperties(): void
    {
        if (!property_exists(
            static::class, $parentProperty = static::getParentProperty()
        ) || !property_exists(
            static::class, $childrenProperty = static::getChildrenProperty()
        )) {
            throw new LogicException(
                sprintf(
                    'Undefined property: %s::$%s',
                    static::class,
                    $childrenProperty ?? $parentProperty
                )
            );
        }

        self::$_ParentProperties[static::class] = $parentProperty;
        self::$_ChildrenProperties[static::class] = $childrenProperty;
    }

    /**
     * @return static|null
     */
    final public function getParent()
    {
        if (!isset(self::$_ParentProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_parent = self::$_ParentProperties[static::class];

        return $this->{$_parent};
    }

    /**
     * @return static[]
     */
    final public function getChildren(): array
    {
        if (!isset(self::$_ChildrenProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_children = self::$_ChildrenProperties[static::class];

        return $this->{$_children} ?: [];
    }

    /**
     * @param (IHierarchy&static)|null $parent
     * @return $this
     */
    final public function setParent($parent)
    {
        if (!isset(self::$_ParentProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_parent = self::$_ParentProperties[static::class];
        $_children = self::$_ChildrenProperties[static::class];

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
        if (!isset(self::$_ParentProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_parent = self::$_ParentProperties[static::class];

        if ($child->{$_parent} !== $this) {
            throw new LogicException('Argument #1 ($child) is not a child of this object');
        }

        return $child->setParent(null);
    }

    final public function getDepth(): int
    {
        if (!isset(self::$_ParentProperties[static::class])) {
            static::loadHierarchyProperties();
        }

        $_parent = self::$_ParentProperties[static::class];

        $depth = 0;
        $parent = $this->{$_parent};
        while (!is_null($parent)) {
            $depth++;
            $parent = $parent->{$_parent};
        }

        return $depth;
    }
}
