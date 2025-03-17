<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Analog\Analog;

/**
 * Writes console output to Analog
 */
final class AnalogTarget extends AbstractTarget
{
    /**
     * @inheritDoc
     */
    public function write(int $level, string $message, array $context = []): void
    {
        Analog::log($message, $level);
    }
}
