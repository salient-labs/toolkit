<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Salient\Container\ContainerInterface;
use Salient\Core\Catalog\ListConformity;

/**
 * @api
 */
interface Constructible
{
    /**
     * @param mixed[] $data
     * @return static
     */
    public static function construct(array $data, ?ContainerInterface $container = null);

    /**
     * @param iterable<mixed[]> $list
     * @param ListConformity::* $conformity
     * @return iterable<static>
     */
    public static function constructList(
        iterable $list,
        $conformity = ListConformity::NONE,
        ?ContainerInterface $container = null
    ): iterable;
}
