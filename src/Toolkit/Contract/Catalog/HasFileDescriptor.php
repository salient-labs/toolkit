<?php declare(strict_types=1);

namespace Salient\Contract\Catalog;

/**
 * @api
 */
interface HasFileDescriptor
{
    public const STDIN = 0;
    public const STDOUT = 1;
    public const STDERR = 2;
}
