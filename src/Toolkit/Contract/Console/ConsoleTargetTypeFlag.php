<?php declare(strict_types=1);

namespace Salient\Contract\Console;

/**
 * Console output target type flags
 */
interface ConsoleTargetTypeFlag
{
    public const TARGET_STREAM = 1;
    public const TARGET_STDIO = 2;
    public const TARGET_STDOUT = 4;
    public const TARGET_STDERR = 8;
    public const TARGET_TTY = 16;
    public const TARGET_INVERT = 32;
}
