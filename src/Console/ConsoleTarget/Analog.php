<?php

declare(strict_types=1);

namespace Lkrms\Console\ConsoleTarget;

/**
 * Write to Analog
 *
 * @package Lkrms\Console
 */
class Analog extends \Lkrms\Console\ConsoleTarget
{
    protected function WriteToTarget(int $level, string $message, array $context)
    {
        // Analog's level constants have the same values as equivalent
        // ConsoleLevel constants
        \Analog\Analog::log($message, $level);
    }
}

