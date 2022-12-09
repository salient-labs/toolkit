<?php declare(strict_types=1);

namespace Lkrms\Console\Target;

use Lkrms\Console\Concept\ConsoleTarget;

/**
 * Write console messages to Analog
 *
 */
final class AnalogTarget extends ConsoleTarget
{
    protected function writeToTarget(int $level, string $message, array $context)
    {
        // Analog's level constants have the same values as equivalent
        // ConsoleLevel constants
        \Analog\Analog::log($message, $level);
    }
}
