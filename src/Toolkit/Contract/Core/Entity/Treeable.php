<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

use Salient\Contract\Core\Hierarchical;

/**
 * @api
 */
interface Treeable extends Hierarchical, Relatable
{
    /**
     * Get the property that links children to a parent of the same type
     *
     * The property returned must accept `static|null`.
     */
    public static function getParentProperty(): string;

    /**
     * Get the property that links a parent to children of the same type
     *
     * The property returned must accept `iterable<static>`.
     */
    public static function getChildrenProperty(): string;
}
