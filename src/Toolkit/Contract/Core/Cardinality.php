<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * Entity relationship cardinalities
 */
interface Cardinality
{
    public const ONE_TO_ONE = 0;
    public const ONE_TO_MANY = 1;
}
