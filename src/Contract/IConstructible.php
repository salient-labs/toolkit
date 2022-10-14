<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\ArrayKeyConformity;

/**
 * Instantiates itself from associative arrays
 *
 */
interface IConstructible
{
    /**
     * @return static
     */
    public static function construct(array $data, ?IContainer $container = null);

    /**
     * @param iterable<array> $dataList
     * @return iterable<static>
     */
    public static function constructList(iterable $dataList, int $conformity = ArrayKeyConformity::NONE, ?IContainer $container = null): iterable;

}
