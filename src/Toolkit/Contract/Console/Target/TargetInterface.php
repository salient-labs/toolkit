<?php declare(strict_types=1);

namespace Salient\Contract\Console\Target;

use Salient\Contract\Console\Format\FormatterInterface;
use Salient\Contract\HasMessageLevel;

/**
 * @api
 */
interface TargetInterface extends HasMessageLevel
{
    /**
     * Get the target's output formatter
     */
    public function getFormatter(): FormatterInterface;

    /**
     * Get the width of the target in columns, or null if output written to the
     * target should not be wrapped
     *
     * Output written to the target may exceed its width, e.g. if a message
     * contains preformatted text or a long word, but long lines may be
     * truncated if the target cannot display or record them.
     */
    public function getWidth(): ?int;

    /**
     * Write formatted output to the target
     *
     * @param TargetInterface::LEVEL_* $level
     * @param array<string,mixed> $context
     */
    public function write(int $level, string $message, array $context = []): void;

    /**
     * Close the target and any underlying resources
     */
    public function close(): void;
}
