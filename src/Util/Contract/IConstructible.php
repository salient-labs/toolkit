<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\Catalog\ArrayKeyConformity;
use Salient\Container\ContainerInterface;

/**
 * Creates instances of itself from data in associative arrays
 */
interface IConstructible
{
    /**
     * @param mixed[] $data
     * @return static
     */
    public static function construct(
        array $data,
        ?ContainerInterface $container = null
    );

    /**
     * @param iterable<mixed[]> $list
     * @param ArrayKeyConformity::* $conformity
     * @return iterable<static>
     */
    public static function constructList(
        iterable $list,
        $conformity = ArrayKeyConformity::NONE,
        ?ContainerInterface $container = null
    ): iterable;
}
