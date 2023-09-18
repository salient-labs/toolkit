<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\Catalog\ArrayKeyConformity;

/**
 * Creates instances of itself from data in associative arrays
 *
 */
interface IConstructible
{
    /**
     * @param mixed[] $data
     * @return static
     */
    public static function construct(
        array $data,
        ?IContainer $container = null
    );

    /**
     * @param iterable<mixed[]> $list
     * @param ArrayKeyConformity::* $conformity
     * @return iterable<static>
     */
    public static function constructList(
        iterable $list,
        int $conformity = ArrayKeyConformity::NONE,
        ?IContainer $container = null
    ): iterable;
}
