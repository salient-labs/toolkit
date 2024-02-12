<?php declare(strict_types=1);

namespace Lkrms\Console\Contract;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\ConsoleFormatter as Formatter;

/**
 * A console output target
 *
 * @api
 */
interface ConsoleTargetInterface
{
    /**
     * Get an output formatter for the target
     */
    public function getFormatter(): Formatter;

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
     * @param Level::* $level
     * @param array<string,mixed> $context
     */
    public function write($level, string $message, array $context = []): void;

    /**
     * Close the target and any underlying resources
     */
    public function close(): void;
}
