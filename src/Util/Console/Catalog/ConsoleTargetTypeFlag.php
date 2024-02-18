<?php declare(strict_types=1);

namespace Lkrms\Console\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Console output target type flags
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class ConsoleTargetTypeFlag extends AbstractEnumeration
{
    public const STREAM = 1;
    public const STDIO = 2;
    public const STDOUT = 4;
    public const STDERR = 8;
    public const TTY = 16;
    public const INVERT = 32;
}
