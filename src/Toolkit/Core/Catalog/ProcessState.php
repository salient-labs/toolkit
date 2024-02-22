<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Process states
 *
 * @extends AbstractEnumeration<int>
 */
final class ProcessState extends AbstractEnumeration
{
    public const READY = 0;
    public const RUNNING = 1;
    public const TERMINATED = 2;
}
