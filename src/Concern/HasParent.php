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
        (self::$_ParentProperties[static::class] ?? null) !== null ||
            static::loadHierarchyProperties();

        return $this->{self::$_ParentProperties[static::class]};
    }

    /**
     * @return static[]
     */
    final public function getChildren(): array
    {
        (self::$_ChildrenProperties[static::class] ?? null) !== null ||
            static::loadHierarchyProperties();

        return $this->{self::$_ChildrenProperties[static::class]} ?: [];
    }

    /**
     * @param static|null $parent
     * @return $this
     */
    final public function setParent($parent)
    {
        (self::$_ParentProperties[static::class] ?? null) !== null ||
            static::loadHierarchyProperties();

        if ($parent === $this->{self::$_ParentProperties[static::class]} &&
                ($parent === null || in_array(
                    $this,
                    $parent->{self::$_ChildrenProperties[static::class]} ?: [],
                    true
                ))) {
            return $this;
        }

        // Remove the object from its current parent
        if ($this->{self::$_ParentProperties[static::class]} !== null) {
            $this
                ->{self::$_ParentProperties[static::class]}
                ->{self::$_ChildrenProperties[static::class]} =
                array_values(
                    array_filter(
                        $this
                            ->{self::$_ParentProperties[static::class]}
                            ->{self::$_ChildrenProperties[static::class]},
                        fn($child) => $child !== $this
                    )
                );
        }

        $this->{self::$_ParentProperties[static::class]} = $parent;

        if ($parent !== null) {
            return $this
                ->{self::$_ParentProperties[static::class]}
                ->{self::$_ChildrenProperties[static::class]}[] = $this;
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
        (self::$_ParentProperties[static::class] ?? null) !== null ||
            static::loadHierarchyProperties();

        if ($child->{self::$_ParentProperties[static::class]} !== $this) {
            throw new LogicException('Argument #1 ($child) is not a child of this object');
        }

        return $child->setParent(null);
    }

    final public function getDepth(): int
    {
        (self::$_ParentProperties[static::class] ?? null) !== null ||
            static::loadHierarchyProperties();

        $depth = 0;
        $parent = $this->{self::$_ParentProperties[static::class]};
        while (!is_null($parent)) {
            $depth++;
            $parent = $parent->{self::$_ParentProperties[static::class]};
        }

        return $depth;
    }
}
