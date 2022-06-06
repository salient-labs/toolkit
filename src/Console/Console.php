<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\ConsoleColour as C;
use Lkrms\Console\ConsoleTarget\ConsoleTarget;
use Lkrms\Console\ConsoleTarget\StreamTarget;
use Lkrms\Util\Env;
use Lkrms\Util\File;
use Lkrms\Util\Generate;
use Throwable;

/**
 * Log various message types to various targets
 *
 */
final class Console extends ConsoleMessageWriter
{
    /**
     * @var ConsoleTarget[]
     */
    private static $OutputTargets = [];

    /**
     * @var ConsoleTarget[]
     */
    private static $Targets = [];

    /**
     * @var array<string,int>
     */
    private static $LoggedOnce = [];

    /**
     * message level => [$msg1 colour, $msg2 colour, prefix colour]
     */
    protected const COLOUR_MAP = [
        ConsoleLevel::ERROR   => [C::BOLD . C::RED, "", C::BOLD . C::RED],
        ConsoleLevel::WARNING => [C::BOLD . C::YELLOW, "", C::BOLD . C::YELLOW],
        ConsoleLevel::NOTICE  => [C::BOLD, C::CYAN, null],
        ConsoleLevel::INFO    => ["", C::YELLOW, null],
        ConsoleLevel::DEBUG   => [C::DIM, C::DIM, null],
    ];

    private static function registerTargets()
    {
        // Log output to `{TMPDIR}/<basename>-<realpath_hash>-<user_id>.log`
        self::registerTarget(StreamTarget::fromPath(File::getStablePath(".log")));
        self::registerOutputStreams();
    }

    /**
     * Register output streams as targets if running on the command line
     *
     * @param bool $ignoreSapi If set, send {@see Console} messages to STDERR
     * and/or STDOUT even if not running on the command line
     */
    public static function registerOutputStreams(bool $ignoreSapi = false)
    {
        // Return if STDOUT or STDERR targets have already been registered
        if ((!$ignoreSapi && PHP_SAPI != "cli") || self::$OutputTargets)
        {
            return;
        }

        // If one, and only one, of STDOUT and STDERR is an interactive terminal
        // (e.g. STDOUT has been redirected to a file, or STDERR is being piped
        // to another process), send Console messages to STDERR only
        if (posix_isatty(STDOUT) xor posix_isatty(STDERR))
        {
            self::registerTarget(new StreamTarget(STDERR,
                Env::debug() ? ConsoleLevels::ALL_DEBUG : ConsoleLevels::ALL));
            return;
        }

        // Otherwise, send errors and warnings to STDERR, everything else to
        // STDOUT
        self::registerTarget(new StreamTarget(STDERR, ConsoleLevels::ERRORS));
        self::registerTarget(new StreamTarget(STDOUT,
            Env::debug() ? ConsoleLevels::INFO_DEBUG : ConsoleLevels::INFO));
    }

    /**
     * Get registered targets backed by STDOUT or STDERR
     *
     * @return ConsoleTarget[]
     */
    public static function getOutputTargets(): array
    {
        if (!self::$Targets)
        {
            self::registerTargets();
        }

        return self::$OutputTargets;
    }

    /**
     * Get registered targets
     *
     * @return ConsoleTarget[]
     */
    public static function getTargets(): array
    {
        if (!self::$Targets)
        {
            self::registerTargets();
        }

        return self::$Targets;
    }

    public static function registerTarget(ConsoleTarget $target)
    {
        if ($target->isStdout() || $target->isStderr())
        {
            self::$OutputTargets[] = $target;
        }

        self::$Targets[] = $target;
    }

    protected static function logWrite(
        string $method,
        string $msg1,
        ?string $msg2
    ): int
    {
        $hash = Generate::hash($method, $msg1, $msg2);

        if (!array_key_exists($hash, self::$LoggedOnce))
        {
            self::$LoggedOnce[$hash] = 0;
        }

        return self::$LoggedOnce[$hash]++;
    }

    /**
     *
     * @param int $level
     * @param string $msg1 Message.
     * @param null|string $msg2 Secondary message.
     * @param string $prefix Prefix.
     * @param Throwable|null $ex Associated exception.
     * @param bool $ttyOnly
     */
    protected static function write(
        int $level,
        string $msg1,
        ?string $msg2,
        string $prefix,
        Throwable $ex = null,
        bool $ttyOnly = false
    ) {
        list ($clr1, $clr2, $clrP) = self::COLOUR_MAP[$level];

        $clr1    = !ConsoleText::hasBold($msg1) ? $clr1 : str_replace(ConsoleColour::BOLD, "", $clr1);
        $clrP    = !is_null($clrP) ? $clrP : ConsoleColour::BOLD . $clr2;
        $ttyMsg1 = ConsoleText::formatColour($msg1);
        $msg1    = ConsoleText::formatPlain($msg1);
        $ttyMsg2 = null;

        $margin = max(0, self::$GroupLevel) * 4;
        $indent = strlen($prefix);
        $indent = max(0, strpos($msg1, "\n") !== false ? $indent : $indent - 4);

        if ($margin + $indent)
        {
            foreach ([&$msg1, &$ttyMsg1] as &$msgRef)
            {
                $msgRef = str_replace("\n", "\n" . str_repeat(" ", $margin + $indent), $msgRef);
            }
        }

        if ($msg2)
        {
            $ttyMsg2 = ConsoleText::formatColour($msg2);
            $msg2    = ConsoleText::formatPlain($msg2);

            foreach ([&$msg2, &$ttyMsg2] as &$msgRef)
            {
                if (strpos($msgRef, "\n") !== false)
                {
                    $msgRef = str_replace("\n", "\n" . str_repeat(" ", $margin + $indent + 2), "\n" . ltrim($msgRef));
                }
                else
                {
                    $msgRef = " " . $msgRef;
                }
            }
        }

        unset($msgRef);
        $msg    = str_repeat(" ", $margin) . $prefix . $msg1 . ($msg2 ?: "");
        $ttyMsg = (str_repeat(" ", $margin)
            . $clrP . $prefix . ($clrP ? ConsoleColour::RESET : "")
            . $clr1 . $ttyMsg1 . ($clr1 ? ConsoleColour::RESET : "")
            . ($msg2 ? $clr2 . $ttyMsg2 . ($clr2 ? ConsoleColour::RESET : "") : ""));

        if ($ex)
        {
            $context = ["exception" => $ex];
        }

        self::print($msg, $ttyMsg, $level, $context ?? [], $ttyOnly);
    }

    /**
     *
     * @param string $plain
     * @param null|string $tty
     * @param int $level
     * @param array $context
     * @param bool $ttyOnly
     * @param ConsoleTarget[]|null $targets
     */
    private static function print(
        string $plain,
        ?string $tty,
        int $level,
        array $context,
        bool $ttyOnly,
        array $targets = null
    ) {
        if (is_null($tty))
        {
            $tty = $plain;
        }

        if (is_null($targets))
        {
            if (!self::$Targets)
            {
                self::registerTargets();
            }
            $targets = self::$Targets;
        }

        foreach ($targets as $target)
        {
            if ($ttyOnly && !$target->isTty())
            {
                continue;
            }

            if ($target->supportsColour())
            {
                $target->write($tty, $context, $level);
            }
            else
            {
                $target->write($plain, $context, $level);
            }
        }
    }

    public static function printTo(
        string $msg,
        ConsoleTarget ...$targets
    ) {
        $ttyMsg = ConsoleText::formatColour($msg);
        $msg    = ConsoleText::formatPlain($msg);

        self::print($msg, $ttyMsg, ConsoleLevel::INFO, [], false, $targets ?: null);
    }
}
