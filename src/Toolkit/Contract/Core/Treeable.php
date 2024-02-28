<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * Has a parent and children of the same type and can be traversed towards them
 * via public properties
 *
 * The properties need not be declared if the class uses property overloading.
 */
interface Treeable extends HierarchyInterface, Relatable
{
    /**
     * Get the name of the property that links the object to a parent of the
     * same type
     *
     * The property should accept values of type `static|null`.
     */
    public static function getParentProperty(): string;

    /**
     * Get the name of the property that links the object to children of the
     * same type
     *
     * The property should accept values of type `iterable<static>`.
     */
    public static function getChildrenProperty(): string;
}
