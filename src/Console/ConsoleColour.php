<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Concept\Enumeration;

/**
 * Escape sequences to set and clear terminal display attributes
 *
 */
final class ConsoleColour extends Enumeration
{
    public const BLACK      = "\033[30m";
    public const RED        = "\033[31m";
    public const GREEN      = "\033[32m";
    public const YELLOW     = "\033[33m";
    public const BLUE       = "\033[34m";
    public const MAGENTA    = "\033[35m";
    public const CYAN       = "\033[36m";
    public const WHITE      = "\033[37m";
    public const DEFAULT    = "\033[39m";
    public const GREY       = "\033[90m";
    public const BLACK_BG   = "\033[40m";
    public const RED_BG     = "\033[41m";
    public const GREEN_BG   = "\033[42m";
    public const YELLOW_BG  = "\033[43m";
    public const BLUE_BG    = "\033[44m";
    public const MAGENTA_BG = "\033[45m";
    public const CYAN_BG    = "\033[46m";
    public const WHITE_BG   = "\033[47m";
    public const DEFAULT_BG = "\033[49m";
    public const GREY_BG    = "\033[100m";
    public const BOLD       = "\033[1m";
    public const UNBOLD     = "\033[22m";
    public const DIM        = "\033[2m";
    public const UNDIM      = "\033[22m";
    public const RESET      = "\033[m";
}
