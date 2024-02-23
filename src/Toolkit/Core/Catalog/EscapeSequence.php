<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Salient\Core\AbstractDictionary;

/**
 * ANSI escape sequences for formatting terminal output
 *
 * @extends AbstractDictionary<string>
 */
final class EscapeSequence extends AbstractDictionary
{
    public const BLACK = "\e[30m";
    public const RED = "\e[31m";
    public const GREEN = "\e[32m";
    public const YELLOW = "\e[33m";
    public const BLUE = "\e[34m";
    public const MAGENTA = "\e[35m";
    public const CYAN = "\e[36m";
    public const WHITE = "\e[37m";
    public const DEFAULT = "\e[39m";
    public const BLACK_BG = "\e[40m";
    public const RED_BG = "\e[41m";
    public const GREEN_BG = "\e[42m";
    public const YELLOW_BG = "\e[43m";
    public const BLUE_BG = "\e[44m";
    public const MAGENTA_BG = "\e[45m";
    public const CYAN_BG = "\e[46m";
    public const WHITE_BG = "\e[47m";
    public const DEFAULT_BG = "\e[49m";
    public const BOLD = "\e[1m";
    public const DIM = "\e[2m";
    public const UNDERLINE = "\e[4m";
    public const NO_UNDERLINE = "\e[24m";

    /**
     * Reset BOLD and DIM
     *
     * @see EscapeSequence::UNBOLD_DIM
     * @see EscapeSequence::UNDIM_BOLD
     */
    public const UNBOLD_UNDIM = "\e[22m";

    /**
     * Reset BOLD, set DIM
     */
    public const UNBOLD_DIM = "\e[22;2m";

    /**
     * Reset DIM, set BOLD
     */
    public const UNDIM_BOLD = "\e[22;1m";

    /**
     * Reset all colours and styles
     */
    public const RESET = "\e[m";

    /**
     * Clear to end of line
     */
    public const CLEAR_LINE = "\e[K";

    /**
     * Turn off automatic margins
     */
    public const WRAP_OFF = "\e[?7l";

    /**
     * Turn on automatic margins
     */
    public const WRAP_ON = "\e[?7h";
}
