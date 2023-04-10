<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Console\Concept\ConsoleTarget;
use Lkrms\Console\ConsoleLevel as Level;
use Lkrms\Console\ConsoleLevels;
use Lkrms\Console\ConsoleWriter;
use Throwable;

/**
 * A facade for \Lkrms\Console\ConsoleWriter
 *
 * @method static ConsoleWriter load() Load and return an instance of the underlying ConsoleWriter class
 * @method static ConsoleWriter getInstance() Get the underlying ConsoleWriter instance
 * @method static bool isLoaded() True if an underlying ConsoleWriter instance has been loaded
 * @method static void unload() Clear the underlying ConsoleWriter instance
 * @method static ConsoleWriter count(int $level) Increment the message counter for $level without printing anything
 * @method static ConsoleWriter debug(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0) Print "--- {CALLER} $msg1 $msg2" with level DEBUG (see {@see ConsoleWriter::debug()})
 * @method static ConsoleWriter debugOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0) Print "--- {CALLER} $msg1 $msg2" with level DEBUG once per run (see {@see ConsoleWriter::debugOnce()})
 * @method static ConsoleWriter error(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true) Print " !! $msg1 $msg2" with level ERROR
 * @method static ConsoleWriter errorOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true) Print " !! $msg1 $msg2" with level ERROR once per run
 * @method static ConsoleWriter exception(Throwable $exception, int $messageLevel = Level::ERROR, ?int $stackTraceLevel = Level::DEBUG) Report an uncaught exception (see {@see ConsoleWriter::exception()})
 * @method static int getErrors() Get the number of errors reported so far
 * @method static int getWarnings() Get the number of warnings reported so far
 * @method static ConsoleWriter group(string $msg1, ?string $msg2 = null) Create a new message group and print "<<< $msg1 $msg2" with level NOTICE (see {@see ConsoleWriter::group()})
 * @method static ConsoleWriter groupEnd(bool $printMessage = false) Close the most recently created message group (see {@see ConsoleWriter::groupEnd()})
 * @method static ConsoleWriter info(string $msg1, ?string $msg2 = null) Print "==> $msg1 $msg2" with level NOTICE
 * @method static ConsoleWriter infoOnce(string $msg1, ?string $msg2 = null) Print "==> $msg1 $msg2" with level NOTICE once per run
 * @method static ConsoleWriter log(string $msg1, ?string $msg2 = null) Print " -> $msg1 $msg2" with level INFO
 * @method static ConsoleWriter logOnce(string $msg1, ?string $msg2 = null) Print " -> $msg1 $msg2" with level INFO once per run
 * @method static ConsoleWriter logProgress(string $msg1, ?string $msg2 = null) Print " -> $msg1 $msg2" with level INFO to TTY targets without moving to the next line (see {@see ConsoleWriter::logProgress()})
 * @method static ConsoleWriter maybeClearLine() Print a "clear to end of line" control sequence with level INFO to any TTY targets with a pending logProgress() message (see {@see ConsoleWriter::maybeClearLine()})
 * @method static ConsoleWriter message(int $level, string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $prefixByLevel = true, bool $formatByLevel = true) Print "$msg1 $msg2" with prefix and formatting optionally based on $level (see {@see ConsoleWriter::message()})
 * @method static ConsoleWriter messageOnce(int $level, string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $prefixByLevel = true, bool $formatByLevel = true) Print "$msg1 $msg2" with prefix and formatting optionally based on $level once per run (see {@see ConsoleWriter::messageOnce()})
 * @method static ConsoleWriter out(string $msg, int $level = Level::INFO, bool $formatByLevel = true) Print "$msg" to I/O stream targets (STDOUT or STDERR)
 * @method static ConsoleWriter print(string $msg, int $level = Level::INFO, bool $formatByLevel = true) Print "$msg"
 * @method static ConsoleWriter registerDefaultStdioTargets(bool $replace = false) Register STDOUT and/or STDERR as targets in their default configuration (see {@see ConsoleWriter::registerDefaultStdioTargets()})
 * @method static ConsoleWriter registerStderrTarget(bool $replace = false) Register STDERR as a target if running on the command line (see {@see ConsoleWriter::registerStderrTarget()})
 * @method static ConsoleWriter registerStdioTargets(bool $replace = false) Register STDOUT and STDERR as targets if running on the command line (see {@see ConsoleWriter::registerStdioTargets()})
 * @method static ConsoleWriter registerTarget(ConsoleTarget $target, array $levels = ConsoleLevels::ALL_DEBUG) A facade for ConsoleWriter::registerTarget()
 * @method static ConsoleWriter setTargetPrefix(?string $prefix, bool $ttyOnly = false, bool $stdio = true, bool $exceptStdio = true) Call setPrefix on registered targets (see {@see ConsoleWriter::setTargetPrefix()})
 * @method static ConsoleWriter summary(string $finishedText = 'Command finished', string $successText = 'without errors') Print a "command finished" message with a summary of errors and warnings (see {@see ConsoleWriter::summary()})
 * @method static ConsoleWriter tty(string $msg, int $level = Level::INFO, bool $formatByLevel = true) Print "$msg" to TTY targets
 * @method static ConsoleWriter warn(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true) Print "  ! $msg1 $msg2" with level WARNING
 * @method static ConsoleWriter warnOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true) Print "  ! $msg1 $msg2" with level WARNING once per run
 *
 * @uses ConsoleWriter
 *
 * @extends Facade<ConsoleWriter>
 *
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Console\ConsoleWriter' 'Lkrms\Facade\Console'
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
