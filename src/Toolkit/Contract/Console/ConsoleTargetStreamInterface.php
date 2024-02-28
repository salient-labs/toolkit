<?php declare(strict_types=1);

namespace Salient\Contract\Console;

/**
 * A console output target with an underlying PHP stream
 *
 * @api
 */
interface ConsoleTargetStreamInterface extends ConsoleTargetInterface
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
     * Close and reopen the underlying PHP stream if possible
     */
    public function reopen(): void;
}
