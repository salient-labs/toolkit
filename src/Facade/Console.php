<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Console\ConsoleLevel as Level;
use Lkrms\Console\ConsoleLevels;
use Lkrms\Console\ConsoleWriter;
use Lkrms\Console\Target\ConsoleTarget;
use Throwable;

/**
 * A facade for \Lkrms\Console\Console
 *
 * @method static \Lkrms\Console\Console load() Load and return an instance of the underlying Console class
 * @method static \Lkrms\Console\Console getInstance() Return the underlying Console instance
 * @method static bool isLoaded() Return true if an underlying Console instance has been loaded
 * @method static void unload() Clear the underlying Console instance
 * @method static \Lkrms\Console\Console debug(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0) Print "--- {CALLER} $msg1 $msg2" with level DEBUG (see {@see ConsoleWriter::debug()})
 * @method static \Lkrms\Console\Console debugOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0) Print "--- {CALLER} $msg1 $msg2" with level DEBUG once per run (see {@see ConsoleWriter::debugOnce()})
 * @method static \Lkrms\Console\Console error(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print " !! $msg1 $msg2" with level ERROR (see {@see ConsoleWriter::error()})
 * @method static \Lkrms\Console\Console errorOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print " !! $msg1 $msg2" with level ERROR once per run (see {@see ConsoleWriter::errorOnce()})
 * @method static \Lkrms\Console\Console exception(Throwable $exception) Report an uncaught exception (see {@see ConsoleWriter::exception()})
 * @method static int getErrors() Get the number of errors reported so far (see {@see ConsoleWriter::getErrors()})
 * @method static int getWarnings() Get the number of warnings reported so far (see {@see ConsoleWriter::getWarnings()})
 * @method static \Lkrms\Console\Console group(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Create a new message group and print "<<< $msg1 $msg2" with level NOTICE (see {@see ConsoleWriter::group()})
 * @method static \Lkrms\Console\Console groupEnd() Close the most recently created message group (see {@see ConsoleWriter::groupEnd()})
 * @method static \Lkrms\Console\Console info(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print "==> $msg1 $msg2" with level NOTICE (see {@see ConsoleWriter::info()})
 * @method static \Lkrms\Console\Console infoOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print "==> $msg1 $msg2" with level NOTICE once per run (see {@see ConsoleWriter::infoOnce()})
 * @method static \Lkrms\Console\Console log(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print " -> $msg1 $msg2" with level INFO (see {@see ConsoleWriter::log()})
 * @method static \Lkrms\Console\Console logOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print " -> $msg1 $msg2" with level INFO once per run (see {@see ConsoleWriter::logOnce()})
 * @method static \Lkrms\Console\Console logProgress(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print " -> $msg1 $msg2" with level INFO to TTY targets (see {@see ConsoleWriter::logProgress()})
 * @method static \Lkrms\Console\Console out(string $msg, int $level = Level::INFO) Print "$msg" to I/O stream targets (STDOUT or STDERR) (see {@see \Lkrms\Console\Console::out()})
 * @method static \Lkrms\Console\Console registerStdioTargets() Register STDOUT and STDERR as targets if running on the command line (see {@see \Lkrms\Console\Console::registerStdioTargets()})
 * @method static \Lkrms\Console\Console registerTarget(ConsoleTarget $target, array $levels = ConsoleLevels::ALL_DEBUG) See {@see \Lkrms\Console\Console::registerTarget()}
 * @method static \Lkrms\Console\Console summary(string $finishedText = 'Command finished', string $successText = 'without errors') Print a "command finished" message with a summary of errors and warnings (see {@see ConsoleWriter::summary()})
 * @method static \Lkrms\Console\Console tty(string $msg, int $level = Level::INFO) Print "$msg" to TTY targets (see {@see \Lkrms\Console\Console::tty()})
 * @method static \Lkrms\Console\Console warn(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print "  ! $msg1 $msg2" with level WARNING (see {@see ConsoleWriter::warn()})
 * @method static \Lkrms\Console\Console warnOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print "  ! $msg1 $msg2" with level WARNING once per run (see {@see ConsoleWriter::warnOnce()})
 *
 * @uses \Lkrms\Console\Console
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Console\Console' --generate='Lkrms\Facade\Console'
 */
final class Console extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return \Lkrms\Console\Console::class;
    }
}
