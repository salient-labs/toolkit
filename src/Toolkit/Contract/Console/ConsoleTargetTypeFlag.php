<?php declare(strict_types=1);

namespace Salient\Contract\Console;

/**
 * Console output target type flags
 */
interface ConsoleTargetTypeFlag
{
    public const STREAM = 1;
    public const STDIO = 2;
    public const STDOUT = 4;
    public const STDERR = 8;
    public const TTY = 16;
    public const INVERT = 32;
}
