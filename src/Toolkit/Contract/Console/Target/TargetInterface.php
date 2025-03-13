<?php declare(strict_types=1);

namespace Salient\Contract\Console\Target;

use Salient\Contract\Console\Format\ConsoleFormatterInterface as FormatterInterface;
use Salient\Contract\Console\ConsoleInterface as Console;

/**
 * A console output target
 */
interface TargetInterface
{
    /**
     * Get an output formatter for the target
     */
    public function getFormatter(): FormatterInterface;

    /**
     * Get the width of the target in columns
     *
     * Returns `null` if output written to the target should not be wrapped.
     *
     * Output may exceed the width reported by this method, e.g. if a message
     * contains preformatted text or a long word.
     *
     * The target may truncate long lines if they cannot be displayed or
     * recorded.
     */
    public function getWidth(): ?int;

    /**
     * Write formatted output to the target
     *
     * @param Console::LEVEL_* $level
     * @param array<string,mixed> $context
     */
    public function write(int $level, string $message, array $context = []): void;

    /**
     * Close the target and any underlying resources
     */
    public function close(): void;
}
