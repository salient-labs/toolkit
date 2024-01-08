<?php declare(strict_types=1);

namespace Lkrms\Console\Contract;

/**
 * A console output target with an underlying PHP stream
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
}
