<?php declare(strict_types=1);

namespace Salient\Contract\Console\Target;

/**
 * A console output target with an underlying PHP stream
 */
interface StreamTargetInterface extends TargetInterface
{
    /**
     * Check if the target writes to STDOUT
     */
    public function isStdout(): bool;

    /**
     * Check if the target writes to STDERR
     */
    public function isStderr(): bool;

    /**
     * Check if the target writes to a TTY
     */
    public function isTty(): bool;

    /**
     * Get the target's end-of-line sequence
     */
    public function getEol(): string;

    /**
     * Close and reopen the underlying PHP stream if possible
     */
    public function reopen(): void;
}
