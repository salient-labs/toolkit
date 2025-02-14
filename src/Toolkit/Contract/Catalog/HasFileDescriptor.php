<?php declare(strict_types=1);

namespace Salient\Contract\Catalog;

/**
 * @api
 */
interface HasFileDescriptor
{
    public const IN = 0;
    public const OUT = 1;
    public const ERR = 2;
}
