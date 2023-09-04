<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleLevels as Levels;
use Lkrms\Console\Catalog\ConsoleMessageType as Type;
use Lkrms\Console\Contract\IConsoleTarget as Target;
use Lkrms\Console\ConsoleWriter;
use Throwable;

/**
 * A facade for \Lkrms\Console\ConsoleWriter
 *
 * @method static ConsoleWriter load() Load and return an instance of the underlying ConsoleWriter class
 * @method static ConsoleWriter getInstance() Get the underlying ConsoleWriter instance
 * @method static bool isLoaded() True if an underlying ConsoleWriter instance has been loaded
 * @method static void unload() Clear the underlying ConsoleWriter instance
 * @method static ConsoleWriter count(Level::* $level) Increment the message counter for $level without printing anything
 * @method static ConsoleWriter debug(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0) Print "--- {CALLER} $msg1 $msg2" with level DEBUG (see {@see ConsoleWriter::debug()})
 * @method static ConsoleWriter debugOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0) Print "--- {CALLER} $msg1 $msg2" with level DEBUG once per run (see {@see ConsoleWriter::debugOnce()})
 * @method static ConsoleWriter deregisterTarget(Target $target) Deregister a previously registered target
 * @method static ConsoleWriter error(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true) Print " !! $msg1 $msg2" with level ERROR
 * @method static ConsoleWriter errorOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true) Print " !! $msg1 $msg2" with level ERROR once per run
 * @method static ConsoleWriter exception(Throwable $exception, Level::* $messageLevel = Level::ERROR, Level::*|null $stackTraceLevel = Level::DEBUG) Report an uncaught exception (see {@see ConsoleWriter::exception()})
 * @method static int getErrors() Get the number of errors reported so far
 * @method static int getWarnings() Get the number of warnings reported so far
 * @method static int|null getWidth(Level::* $level = Level::INFO) Get the number of columns available for console output
 * @method static ConsoleWriter group(string $msg1, ?string $msg2 = null) Create a new message group and print "<<< $msg1 $msg2" with level NOTICE (see {@see ConsoleWriter::group()})
 * @method static ConsoleWriter groupEnd(bool $printMessage = false) Close the most recently created message group (see {@see ConsoleWriter::groupEnd()})
 * @method static ConsoleWriter info(string $msg1, ?string $msg2 = null) Print "==> $msg1 $msg2" with level NOTICE
 * @method static ConsoleWriter infoOnce(string $msg1, ?string $msg2 = null) Print "==> $msg1 $msg2" with level NOTICE once per run
 * @method static ConsoleWriter log(string $msg1, ?string $msg2 = null) Print " -> $msg1 $msg2" with level INFO
 * @method static ConsoleWriter logOnce(string $msg1, ?string $msg2 = null) Print " -> $msg1 $msg2" with level INFO once per run
 * @method static ConsoleWriter logProgress(string $msg1, ?string $msg2 = null) Print " -> $msg1 $msg2" with level INFO to TTY targets without moving to the next line (see {@see ConsoleWriter::logProgress()})
 * @method static ConsoleWriter maybeClearLine() Print a "clear to end of line" control sequence with level INFO to any TTY targets with a pending logProgress() message (see {@see ConsoleWriter::maybeClearLine()})
 * @method static ConsoleWriter message(Level::* $level, string $msg1, ?string $msg2 = null, Type::* $type = Type::DEFAULT, ?Throwable $ex = null) Print "$msg1 $msg2" with prefix and formatting optionally based on $level (see {@see ConsoleWriter::message()})
 * @method static ConsoleWriter messageOnce(Level::* $level, string $msg1, ?string $msg2 = null, Type::* $type = Type::DEFAULT, ?Throwable $ex = null) Print "$msg1 $msg2" with prefix and formatting optionally based on $level once per run (see {@see ConsoleWriter::messageOnce()})
 * @method static ConsoleWriter out(string $msg, Level::* $level = Level::INFO, Type::* $type = Type::UNDECORATED) Print "$msg" to I/O stream targets (STDOUT or STDERR)
 * @method static ConsoleWriter print(string $msg, Level::* $level = Level::INFO, Type::* $type = Type::UNDECORATED) Print "$msg"
 * @method static ConsoleWriter registerDefaultOutputLog() Register the default output log as a target for all console messages (see {@see ConsoleWriter::registerDefaultOutputLog()})
 * @method static ConsoleWriter registerDefaultStdioTargets(bool $replace = false) Register STDOUT and STDERR as targets in their default configuration (see {@see ConsoleWriter::registerDefaultStdioTargets()})
 * @method static ConsoleWriter registerStderrTarget(bool $replace = false) Register STDERR as a target for all console messages if running on the command line (see {@see ConsoleWriter::registerStderrTarget()})
 * @method static ConsoleWriter registerStdioTargets(bool $replace = false) Register STDOUT and STDERR as targets if running on the command line (see {@see ConsoleWriter::registerStdioTargets()})
 * @method static ConsoleWriter registerTarget(Target $target, array<Level::*> $levels = Levels::ALL) Register a target to receive one or more levels of console messages
 * @method static ConsoleWriter setTargetPrefix(?string $prefix, bool $ttyOnly = false, ?bool $stdio = null) Call setPrefix on registered targets (see {@see ConsoleWriter::setTargetPrefix()})
 * @method static ConsoleWriter stderr(string $msg, Level::* $level = Level::INFO, Type::* $type = Type::UNFORMATTED) Print "$msg" to STDERR, creating a target for it if necessary
 * @method static ConsoleWriter stdout(string $msg, Level::* $level = Level::INFO, Type::* $type = Type::UNFORMATTED) Print "$msg" to STDOUT, creating a target for it if necessary
 * @method static ConsoleWriter summary(string $finishedText = 'Command finished', string $successText = 'without errors') Print a "command finished" message with a summary of errors and warnings (see {@see ConsoleWriter::summary()})
 * @method static ConsoleWriter tty(string $msg, Level::* $level = Level::INFO, Type::* $type = Type::UNDECORATED) Print "$msg" to TTY targets
 * @method static ConsoleWriter warn(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true) Print "  ! $msg1 $msg2" with level WARNING
 * @method static ConsoleWriter warnOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true) Print "  ! $msg1 $msg2" with level WARNING once per run
 *
 * @uses ConsoleWriter
 *
 * @extends Facade<ConsoleWriter>
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
