<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

/**
 * @api
 */
interface Relatable
{
    public const ONE_TO_ONE = 0;
    public const ONE_TO_MANY = 1;

    /**
     * Get an array that maps properties to relationships
     *
     * @return array<string,array<Relatable::*,class-string<Relatable>>>
     */
    public static function getRelationships(): array;
}
