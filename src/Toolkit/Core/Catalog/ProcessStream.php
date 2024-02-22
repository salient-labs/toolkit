<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Process streams
 *
 * @extends AbstractEnumeration<int>
 */
final class ProcessStream extends AbstractEnumeration
{
    public const STDIN = 0;
    public const STDOUT = 1;
    public const STDERR = 2;
}
