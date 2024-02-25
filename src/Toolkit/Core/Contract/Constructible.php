<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Salient\Container\ContainerInterface;
use Salient\Core\Catalog\Conformity;

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
     * @param Conformity::* $conformity
     * @return iterable<static>
     */
    public static function constructList(
        iterable $list,
        $conformity = Conformity::NONE,
        ?ContainerInterface $container = null
    ): iterable;
}
