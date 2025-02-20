<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Analog\Analog;
use Salient\Console\Concept\ConsoleTarget;

/**
 * Writes console output to Analog
 */
final class AnalogTarget extends ConsoleTarget
{
    /**
     * @inheritDoc
     */
    public function write(int $level, string $message, array $context = []): void
    {
        Analog::log($message, $level);
    }
}
