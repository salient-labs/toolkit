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
 * A facade for \Lkrms\Console\ConsoleWriter
 *
 * @method static ConsoleWriter load() Load and return an instance of the underlying ConsoleWriter class
 * @method static ConsoleWriter getInstance() Return the underlying ConsoleWriter instance
 * @method static bool isLoaded() Return true if an underlying ConsoleWriter instance has been loaded
 * @method static void unload() Clear the underlying ConsoleWriter instance
 * @method static ConsoleWriter debug(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0) Print "--- {CALLER} $msg1 $msg2" with level DEBUG (see {@see ConsoleWriter::debug()})
 * @method static ConsoleWriter debugOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0) Print "--- {CALLER} $msg1 $msg2" with level DEBUG once per run (see {@see ConsoleWriter::debugOnce()})
 * @method static ConsoleWriter error(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print " !! $msg1 $msg2" with level ERROR (see {@see ConsoleWriter::error()})
 * @method static ConsoleWriter errorOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print " !! $msg1 $msg2" with level ERROR once per run (see {@see ConsoleWriter::errorOnce()})
 * @method static ConsoleWriter exception(Throwable $exception) Report an uncaught exception (see {@see ConsoleWriter::exception()})
 * @method static int getErrors() Get the number of errors reported so far (see {@see ConsoleWriter::getErrors()})
 * @method static int getWarnings() Get the number of warnings reported so far (see {@see ConsoleWriter::getWarnings()})
 * @method static ConsoleWriter group(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Create a new message group and print "<<< $msg1 $msg2" with level NOTICE (see {@see ConsoleWriter::group()})
 * @method static ConsoleWriter groupEnd() Close the most recently created message group (see {@see ConsoleWriter::groupEnd()})
 * @method static ConsoleWriter info(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print "==> $msg1 $msg2" with level NOTICE (see {@see ConsoleWriter::info()})
 * @method static ConsoleWriter infoOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print "==> $msg1 $msg2" with level NOTICE once per run (see {@see ConsoleWriter::infoOnce()})
 * @method static ConsoleWriter log(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print " -> $msg1 $msg2" with level INFO (see {@see ConsoleWriter::log()})
 * @method static ConsoleWriter logOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print " -> $msg1 $msg2" with level INFO once per run (see {@see ConsoleWriter::logOnce()})
 * @method static ConsoleWriter logProgress(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print " -> $msg1 $msg2" with level INFO to TTY targets (see {@see ConsoleWriter::logProgress()})
 * @method static ConsoleWriter out(string $msg, int $level = Level::INFO) Print "$msg" to I/O stream targets (STDOUT or STDERR) (see {@see ConsoleWriter::out()})
 * @method static ConsoleWriter registerStdioTargets() Register STDOUT and STDERR as targets if running on the command line (see {@see ConsoleWriter::registerStdioTargets()})
 * @method static ConsoleWriter registerTarget(ConsoleTarget $target, array $levels = ConsoleLevels::ALL_DEBUG) See {@see ConsoleWriter::registerTarget()}
 * @method static ConsoleWriter summary(string $finishedText = 'Command finished', string $successText = 'without errors') Print a "command finished" message with a summary of errors and warnings (see {@see ConsoleWriter::summary()})
 * @method static ConsoleWriter tty(string $msg, int $level = Level::INFO) Print "$msg" to TTY targets (see {@see ConsoleWriter::tty()})
 * @method static ConsoleWriter warn(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print "  ! $msg1 $msg2" with level WARNING (see {@see ConsoleWriter::warn()})
 * @method static ConsoleWriter warnOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null) Print "  ! $msg1 $msg2" with level WARNING once per run (see {@see ConsoleWriter::warnOnce()})
 *
 * @uses ConsoleWriter
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Console\ConsoleWriter' --generate='Lkrms\Facade\Console'
 */
final class Console extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return ConsoleWriter::class;
    }
}
