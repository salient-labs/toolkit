<?php declare(strict_types=1);

namespace Lkrms\Console\Target;

use Analog\Analog;
use Lkrms\Console\Concept\ConsoleTarget;

/**
 * Writes console output to Analog
 */
final class AnalogTarget extends ConsoleTarget
{
    /**
     * @inheritDoc
     */
    public function write($level, string $message, array $context = []): void
    {
        Analog::log($message, $level);
    }
}
