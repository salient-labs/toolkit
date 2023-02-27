<?php declare(strict_types=1);

namespace Lkrms\Support\Dictionary;

use Lkrms\Concept\Dictionary;

/**
 * Terminal control sequences
 *
 */
final class TtyControlSequence extends Dictionary
{
    public const BLACK      = "\x1b[30m";
    public const RED        = "\x1b[31m";
    public const GREEN      = "\x1b[32m";
    public const YELLOW     = "\x1b[33m";
    public const BLUE       = "\x1b[34m";
    public const MAGENTA    = "\x1b[35m";
    public const CYAN       = "\x1b[36m";
    public const WHITE      = "\x1b[37m";
    public const DEFAULT    = "\x1b[39m";
    public const GREY       = "\x1b[90m";
    public const BLACK_BG   = "\x1b[40m";
    public const RED_BG     = "\x1b[41m";
    public const GREEN_BG   = "\x1b[42m";
    public const YELLOW_BG  = "\x1b[43m";
    public const BLUE_BG    = "\x1b[44m";
    public const MAGENTA_BG = "\x1b[45m";
    public const CYAN_BG    = "\x1b[46m";
    public const WHITE_BG   = "\x1b[47m";
    public const DEFAULT_BG = "\x1b[49m";
    public const GREY_BG    = "\x1b[100m";
    public const BOLD       = "\x1b[1m";
    public const UNBOLD     = "\x1b[22m";
    public const DIM        = "\x1b[2m";
    public const UNDIM      = "\x1b[22m";

    /**
     * Turn off all attributes
     *
     */
    public const RESET = "\x1b[m";

    /**
     * Clear to end of line
     *
     */
    public const CLEAR_LINE = "\x1b[K";

    /**
     * Turn off automatic margins
     *
     */
    public const WRAP_OFF = "\x1b[?7l";

    /**
     * Turn on automatic margins
     *
     */
    public const WRAP_ON = "\x1b[?7h";
}
