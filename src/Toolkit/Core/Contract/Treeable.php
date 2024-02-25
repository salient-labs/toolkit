<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * Has a parent of the same type and can be traversed towards it via a public
 * property
 *
 * The property need not be declared if the class uses property overloading.
 */
interface Treeable
{
    /**
     * Get the name of the property that links the object to a parent of the
     * same type
     *
     * The property should accept values of type `static|null`.
     */
    public static function getParentProperty(): string;
}
