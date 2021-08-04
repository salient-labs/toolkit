<?php

declare(strict_types=1);

namespace Lkrms;

use DateTime;

/**
 * Functions for console output
 *
 * @package Lkrms
 */
class Console
{
    const BLACK = "\033[30m";

    const RED = "\033[31m";

    const GREEN = "\033[32m";

    const YELLOW = "\033[33m";

    const BLUE = "\033[34m";

    const MAGENTA = "\033[35m";

    const CYAN = "\033[36m";

    const WHITE = "\033[37m";

    const GREY = "\033[90m";

    const BLACK_BG = "\033[40m";

    const RED_BG = "\033[41m";

    const GREEN_BG = "\033[42m";

    const YELLOW_BG = "\033[43m";

    const BLUE_BG = "\033[44m";

    const MAGENTA_BG = "\033[45m";

    const CYAN_BG = "\033[46m";

    const WHITE_BG = "\033[47m";

    const GREY_BG = "\033[100m";

    const BOLD = "\033[1m";

    const DIM = "\033[2m";

    const UNDIM = "\033[22m";

    const RESET = "\033[m\017";

    /**
     * @var int
     */
    private static $GroupDepth = 0;

    /**
     * @var bool
     */
    private static $Timestamp = false;

    public static function EnableTimestamp()
    {
        self::$Timestamp = true;
    }

    public static function DisableTimestamp()
    {
        self::$Timestamp = false;
    }

    private static function Write($stream, string $msg1, ?string $msg2, string $pre = null, string $clr1 = null, string $clr2 = null, string $clrP = null)
    {
        switch (self::$GroupDepth)
        {
            case 0:

                $pre  = ! is_null($pre) ? $pre : "==> ";
                $clr1 = ! is_null($clr1) ? $clr1 : self::BOLD;
                $clr2 = ! is_null($clr2) ? $clr2 : self::CYAN;
                $clrP = ! is_null($clrP) ? $clrP : self::BOLD . $clr2;

                break;

            case 1:

                $pre  = ! is_null($pre) ? $pre : "   -> ";
                $clr1 = ! is_null($clr1) ? $clr1 : "";
                $clr2 = ! is_null($clr2) ? $clr2 : self::YELLOW;
                $clrP = ! is_null($clrP) ? $clrP : self::BOLD . $clr2;

                break;

            default:

                $pre  = ! is_null($pre) ? $pre : "     -- ";
                $clr1 = ! is_null($clr1) ? $clr1 : ( ! is_null($clr2) ? $clr2 : self::YELLOW);
                $clr2 = ! is_null($clr2) ? $clr2 : $clr1;
                $clrP = ! is_null($clrP) ? $clrP : $clr2;

                break;
        }

        $indent  = mb_strlen($pre, "UTF-8");
        $indent  = max(0, strpos($msg1, "\n") ? $indent : $indent - 4);
        $indent += (self::$Timestamp ? 25 : 0);

        if ($indent)
        {
            $msg1 = str_replace("\n", "\n" . str_repeat(" ", $indent), $msg1);
        }

        if ($msg2)
        {
            if (strpos($msg2, "\n"))
            {
                $msg2 = str_replace("\n", "\n" . str_repeat(" ", $indent + 2), "\n" . ltrim($msg2));
            }
            else
            {
                $msg2 = " " . $msg2;
            }
        }

        $pre  = $clrP . $pre . ($clrP ? self::RESET : "");
        $msg1 = $clr1 . $msg1 . ($clr1 ? self::RESET : "");
        $msg2 = $msg2 ? $clr2 . $msg2 . ($clr2 ? self::RESET : "") : "";
        fwrite($stream, (self::$Timestamp ? (new DateTime())->format("[d M H:i:s.u] ") : "") . $pre . $msg1 . $msg2 . "\n");
        fflush($stream);
    }

    public static function Group(string $msg1, string $msg2 = null)
    {
        self::Write(STDOUT, $msg1, $msg2);
        self::$GroupDepth++;
    }

    public static function GroupEnd()
    {
        self::$GroupDepth--;
    }

    public static function Log(string $msg1, string $msg2 = null)
    {
        self::Write(STDOUT, $msg1, $msg2);
    }

    public static function Info(string $msg1, string $msg2 = null)
    {
        self::Write(STDOUT, $msg1, $msg2, null, null, self::GREEN);
    }

    public static function Warn(string $msg1, string $msg2 = null)
    {
        self::Write(STDERR, $msg1, $msg2, "\342\234\230 ", self::YELLOW . self::BOLD, self::BOLD, self::YELLOW . self::BOLD);
    }

    public static function Error(string $msg1, string $msg2 = null)
    {
        self::Write(STDERR, $msg1, $msg2, "\342\234\230 ", self::RED . self::BOLD, self::BOLD, self::RED . self::BOLD);
    }

    public static function Debug(string $msg1, string $msg2 = null)
    {
        self::Write(STDOUT, $msg1, $msg2, null, self::DIM . self::BOLD, self::DIM, self::DIM);
    }
}

