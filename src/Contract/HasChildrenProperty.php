<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Has children of the same type and can be traversed towards them via a public
 * property
 *
 * The property need not be declared if the class uses property overloading.
 */
interface HasChildrenProperty
{
    /**
     * Get the name of the property that links the object to children of the
     * same type
     *
     * The property should accept values of type `iterable<static>`.
     */
    public static function getChildrenProperty(): string;
}
