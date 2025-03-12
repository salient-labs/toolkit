<?php declare(strict_types=1);

namespace Salient\Contract\Console;

/**
 * @api
 */
interface HasTargetFlag
{
    /**
     * Implements StreamTargetInterface
     */
    public const TARGET_STREAM = 1;

    /**
     * Implements StreamTargetInterface and writes to STDOUT or STDERR
     */
    public const TARGET_STDIO = 2;

    /**
     * Implements StreamTargetInterface and writes to STDOUT
     */
    public const TARGET_STDOUT = 4;

    /**
     * Implements StreamTargetInterface and writes to STDERR
     */
    public const TARGET_STDERR = 8;

    /**
     * Implements StreamTargetInterface and writes to a TTY
     */
    public const TARGET_TTY = 16;

    /**
     * Does not match enabled flags
     */
    public const TARGET_INVERT = 32;
}
