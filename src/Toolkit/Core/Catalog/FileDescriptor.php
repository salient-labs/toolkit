<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * File descriptors
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class FileDescriptor extends AbstractEnumeration
{
    public const IN = 0;
    public const OUT = 1;
    public const ERR = 2;
}
