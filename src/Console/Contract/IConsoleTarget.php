<?php declare(strict_types=1);

namespace Lkrms\Console\Contract;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\ConsoleFormatter;

/**
 * A console output target
 */
interface IConsoleTarget
{
    /**
     * True if the target writes to STDOUT
     */
    public function isStdout(): bool;

    /**
     * True if the target writes to STDERR
     */
    public function isStderr(): bool;

    /**
     * True if the target writes to a TTY
     */
    public function isTty(): bool;

    /**
     * Get an output formatter for the target
     */
    public function getFormatter(): ConsoleFormatter;

    /**
     * Get the width of the target
     *
     * Output written to the target may exceed the width reported by this
     * method, e.g. if a message contains preformatted text or a long word.
     *
     * Targets may truncate long lines if they cannot be displayed or recorded.
     *
     * @return int|null `null` if output written to the target should not be
     * wrapped, otherwise an integer representing the number of columns
     * available for console output.
     */
    public function width(): ?int;

    /**
     * Write a formatted message to the target
     *
     * @param Level::* $level
     * @param mixed[] $context
     */
    public function write($level, string $message, array $context = []): void;
}
