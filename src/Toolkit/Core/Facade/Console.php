<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Console\ConsoleFormatter as Formatter;
use Salient\Console\ConsoleWriter;
use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Console\ConsoleTargetInterface as Target;
use Salient\Contract\Console\ConsoleTargetStreamInterface as TargetStream;
use Salient\Contract\Console\ConsoleTargetTypeFlag as TargetTypeFlag;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Core\MessageLevelGroup as LevelGroup;
use Salient\Core\AbstractFacade;
use Throwable;

/**
 * A facade for ConsoleWriter
 *
 * @method static ConsoleWriter count(Level::* $level) Increment the message counter for $level without printing anything
 * @method static ConsoleWriter debug(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0) Print "--- {CALLER} $msg1 $msg2" with level DEBUG (see {@see ConsoleWriter::debug()})
 * @method static ConsoleWriter debugOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0) Print "--- {CALLER} $msg1 $msg2" with level DEBUG once per run (see {@see ConsoleWriter::debugOnce()})
 * @method static ConsoleWriter deregisterAllTargets() Close and deregister all registered targets
 * @method static ConsoleWriter deregisterTarget(Target $target) Close and deregister a previously registered target
 * @method static ConsoleWriter error(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true) Print " !! $msg1 $msg2" with level ERROR
 * @method static ConsoleWriter errorOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true) Print " !! $msg1 $msg2" with level ERROR once per run
 * @method static ConsoleWriter exception(Throwable $exception, Level::* $messageLevel = Level::ERROR, Level::*|null $stackTraceLevel = Level::DEBUG) Report an uncaught exception (see {@see ConsoleWriter::exception()})
 * @method static int getErrors() Get the number of errors reported so far
 * @method static Formatter getFormatter(Level::* $level = Level::INFO) Get an output formatter for a registered target (see {@see ConsoleWriter::getFormatter()})
 * @method static TargetStream getStderrTarget() Get a target for STDERR, creating it if necessary
 * @method static TargetStream getStdoutTarget() Get a target for STDOUT, creating it if necessary
 * @method static Target[] getTargets() Get a list of registered targets
 * @method static int getWarnings() Get the number of warnings reported so far
 * @method static int|null getWidth(Level::* $level = Level::INFO) Get the width of a registered target in columns (see {@see ConsoleWriter::getWidth()})
 * @method static ConsoleWriter group(string $msg1, ?string $msg2 = null) Create a new message group and print "<<< $msg1 $msg2" with level NOTICE (see {@see ConsoleWriter::group()})
 * @method static ConsoleWriter groupEnd(bool $printMessage = false) Close the most recently created message group (see {@see ConsoleWriter::groupEnd()})
 * @method static ConsoleWriter info(string $msg1, ?string $msg2 = null) Print "==> $msg1 $msg2" with level NOTICE
 * @method static ConsoleWriter infoOnce(string $msg1, ?string $msg2 = null) Print "==> $msg1 $msg2" with level NOTICE once per run
 * @method static ConsoleWriter log(string $msg1, ?string $msg2 = null) Print " -> $msg1 $msg2" with level INFO
 * @method static ConsoleWriter logOnce(string $msg1, ?string $msg2 = null) Print " -> $msg1 $msg2" with level INFO once per run
 * @method static ConsoleWriter logProgress(string $msg1, ?string $msg2 = null) Print " -> $msg1 $msg2" with level INFO to TTY targets without moving to the next line (see {@see ConsoleWriter::logProgress()})
 * @method static ConsoleWriter maybeClearLine() Print a "clear to end of line" control sequence with level INFO to any TTY targets with a pending logProgress() message (see {@see ConsoleWriter::maybeClearLine()})
 * @method static ConsoleWriter maybeRegisterStdioTargets(bool $replace = false) Register STDOUT or STDERR to receive console output if a preferred target is found in the environment and no other standard output targets are registered (see {@see ConsoleWriter::maybeRegisterStdioTargets()})
 * @method static ConsoleWriter message(Level::* $level, string $msg1, ?string $msg2 = null, MessageType::* $type = MessageType::STANDARD, ?Throwable $ex = null, bool $count = true) Print "$msg1 $msg2" with prefix and formatting optionally based on $level (see {@see ConsoleWriter::message()})
 * @method static ConsoleWriter messageOnce(Level::* $level, string $msg1, ?string $msg2 = null, MessageType::* $type = MessageType::STANDARD, ?Throwable $ex = null, bool $count = true) Print "$msg1 $msg2" with prefix and formatting optionally based on $level once per run (see {@see ConsoleWriter::messageOnce()})
 * @method static ConsoleWriter out(string $msg, Level::* $level = Level::INFO, MessageType::* $type = MessageType::UNDECORATED) Print "$msg" to I/O stream targets (STDOUT or STDERR)
 * @method static ConsoleWriter print(string $msg, Level::* $level = Level::INFO, MessageType::* $type = MessageType::UNDECORATED) Print "$msg"
 * @method static ConsoleWriter registerLogTarget() Register a log file to receive console output (see {@see ConsoleWriter::registerLogTarget()})
 * @method static ConsoleWriter registerStderrTarget(bool $replace = false) Register STDERR to receive all console output if running on the command line and no other standard output targets are registered (see {@see ConsoleWriter::registerStderrTarget()})
 * @method static ConsoleWriter registerStdioTargets(bool $replace = false) Register STDOUT and STDERR to receive console output if running on the command line and no other standard output targets are registered (see {@see ConsoleWriter::registerStdioTargets()})
 * @method static ConsoleWriter registerTarget(Target $target, array<Level::*> $levels = LevelGroup::ALL) Register a target to receive console output
 * @method static ConsoleWriter setTargetPrefix(?string $prefix, int-mask-of<TargetTypeFlag::*> $flags = 0) Set or unset the prefix applied to each line of output by targets that implement ConsoleTargetPrefixInterface
 * @method static ConsoleWriter stderr(string $msg, Level::* $level = Level::INFO, MessageType::* $type = MessageType::UNFORMATTED) Print "$msg" to STDERR, creating a target for it if necessary
 * @method static ConsoleWriter stdout(string $msg, Level::* $level = Level::INFO, MessageType::* $type = MessageType::UNFORMATTED) Print "$msg" to STDOUT, creating a target for it if necessary
 * @method static ConsoleWriter summary(string $finishedText = 'Command finished', string $successText = 'without errors') Print a "command finished" message with a summary of errors and warnings (see {@see ConsoleWriter::summary()})
 * @method static ConsoleWriter tty(string $msg, Level::* $level = Level::INFO, MessageType::* $type = MessageType::UNDECORATED) Print "$msg" to TTY targets
 * @method static ConsoleWriter warn(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true) Print "  ! $msg1 $msg2" with level WARNING
 * @method static ConsoleWriter warnOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true) Print "  ! $msg1 $msg2" with level WARNING once per run
 *
 * @api
 *
 * @extends AbstractFacade<ConsoleWriter>
 *
 * @generated
 */
final class Console extends AbstractFacade
{
    /**
     * @internal
     */
    protected static function getService()
    {
        return ConsoleWriter::class;
    }
}
