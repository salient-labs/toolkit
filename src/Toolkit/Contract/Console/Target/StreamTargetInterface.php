<?php declare(strict_types=1);

namespace Salient\Contract\Console\Target;

/**
 * @api
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
     * Get the URI or filename of the target's underlying stream, or null if its
     * location is unknown
     */
    public function getUri(): ?string;

    /**
     * Close and reopen the target's underlying stream if possible
     */
    public function reopen(): void;
}
