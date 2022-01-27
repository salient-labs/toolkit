<?php

declare(strict_types=1);

namespace Lkrms\Console\ConsoleTarget;

/**
 * Equivalent to /dev/null
 *
 * @package Lkrms\Console
 */
class NullTarget extends \Lkrms\Console\ConsoleTarget
{
    protected function WriteToTarget(int $level, string $message, array $context)
    {
    }
}

