<?php declare(strict_types=1);

namespace Salient\Contract;

/**
 * @api
 */
interface HasEscapeSequence
{
    public const BLACK_FG = "\e[30m";
    public const RED_FG = "\e[31m";
    public const GREEN_FG = "\e[32m";
    public const YELLOW_FG = "\e[33m";
    public const BLUE_FG = "\e[34m";
    public const MAGENTA_FG = "\e[35m";
    public const CYAN_FG = "\e[36m";
    public const WHITE_FG = "\e[37m";
    public const DEFAULT_FG = "\e[39m";
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
    public const FAINT = "\e[2m";
    public const BOLD_NOT_FAINT = "\e[22;1m";
    public const FAINT_NOT_BOLD = "\e[22;2m";
    public const NOT_BOLD_NOT_FAINT = "\e[22m";
    public const UNDERLINED = "\e[4m";
    public const NOT_UNDERLINED = "\e[24m";

    /**
     * Reset colours and style
     *
     * From \[ECMA-48] 8.3.117 (SELECT GRAPHIC RENDITION): "cancels the effect
     * of any preceding occurrence of SGR in the data stream".
     */
    public const RESET = "\e[m";

    /**
     * Clear to end of line
     *
     * From \[ECMA-48] 8.3.41 (ERASE IN LINE): "the active presentation position
     * and the character positions up to the end of the line are put into the
     * erased state".
     */
    public const CLEAR_LINE = "\e[K";

    /**
     * Disable auto-wrap mode (DECAWM)
     */
    public const NO_AUTO_WRAP = "\e[?7l";

    /**
     * Enable auto-wrap mode (DECAWM)
     */
    public const AUTO_WRAP = "\e[?7h";
}
