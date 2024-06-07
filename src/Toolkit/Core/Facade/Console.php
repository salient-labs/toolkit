<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Psr\Log\LoggerInterface;
use Salient\Console\ConsoleFormatter as Formatter;
use Salient\Console\ConsoleWriter;
use Salient\Contract\Console\ConsoleMessageType;
use Salient\Contract\Console\ConsoleTargetInterface as Target;
use Salient\Contract\Console\ConsoleTargetStreamInterface as TargetStream;
use Salient\Contract\Console\ConsoleTargetTypeFlag;
use Salient\Contract\Core\MessageLevel;
use Salient\Contract\Core\MessageLevelGroup as LevelGroup;
use Salient\Core\AbstractFacade;
use Throwable;

/**
 * A facade for ConsoleWriter
 *
 * @method static ConsoleWriter clearProgress() Print a "clear to end of line" control sequence with level INFO to TTY targets with a logProgress() message
 * @method static ConsoleWriter count(MessageLevel::* $level) Record a message with level $level without printing anything
 * @method static ConsoleWriter debug(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, int $depth = 0) Print "⁞ <caller> $msg1 $msg2" with level DEBUG (see {@see ConsoleWriter::debug()})
 * @method static ConsoleWriter debugOnce(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, int $depth = 0) Print "⁞ <caller> $msg1 $msg2" with level DEBUG once per run (see {@see ConsoleWriter::debugOnce()})
 * @method static ConsoleWriter deregisterTarget(Target $target) Deregister and close a registered target (see {@see ConsoleWriter::deregisterTarget()})
 * @method static ConsoleWriter error(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "‼ $msg1 $msg2" with level ERROR
 * @method static ConsoleWriter errorOnce(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "‼ $msg1 $msg2" with level ERROR once per run
 * @method static ConsoleWriter exception(Throwable $exception, MessageLevel::* $level = MessageLevel::ERROR, MessageLevel::*|null $traceLevel = MessageLevel::DEBUG) Print an exception with level $level (default: ERROR) and its stack trace with level $traceLevel (default: DEBUG) (see {@see ConsoleWriter::exception()})
 * @method static int getErrorCount() Get the number of error messages recorded by the writer so far
 * @method static Formatter getFormatter(MessageLevel::* $level = MessageLevel::INFO) Get an output formatter for a registered target (see {@see ConsoleWriter::getFormatter()})
 * @method static LoggerInterface getLogger() Get a PSR-3 logger backed by the writer
 * @method static TargetStream getStderrTarget() Get a target for STDERR, creating an unregistered one if necessary
 * @method static TargetStream getStdoutTarget() Get a target for STDOUT, creating an unregistered one if necessary
 * @method static Target[] getTargets(MessageLevel::*|null $level = null, int-mask-of<ConsoleTargetTypeFlag::*> $flags = 0) Get a list of registered targets, optionally filtered by level and type
 * @method static int getWarningCount() Get the number of warning messages recorded by the writer so far
 * @method static int|null getWidth(MessageLevel::* $level = MessageLevel::INFO) Get the width of a registered target in columns (see {@see ConsoleWriter::getWidth()})
 * @method static ConsoleWriter group(string $msg1, string|null $msg2 = null, string|null $endMsg1 = null, string|null $endMsg2 = null) Increase the indentation level of messages and print "▶ $msg1 $msg2" with level NOTICE (see {@see ConsoleWriter::group()})
 * @method static ConsoleWriter groupEnd() Close the nested message group most recently opened with group()
 * @method static ConsoleWriter info(string $msg1, string|null $msg2 = null) Print "➤ $msg1 $msg2" with level NOTICE
 * @method static ConsoleWriter infoOnce(string $msg1, string|null $msg2 = null) Print "➤ $msg1 $msg2" with level NOTICE once per run
 * @method static ConsoleWriter log(string $msg1, string|null $msg2 = null) Print "- $msg1 $msg2" with level INFO
 * @method static ConsoleWriter logOnce(string $msg1, string|null $msg2 = null) Print "- $msg1 $msg2" with level INFO once per run
 * @method static ConsoleWriter logProgress(string $msg1, string|null $msg2 = null) Print "⠿ $msg1 $msg2" with level INFO to TTY targets without moving to the next line (see {@see ConsoleWriter::logProgress()})
 * @method static ConsoleWriter message(MessageLevel::* $level, string $msg1, string|null $msg2 = null, ConsoleMessageType::* $type = ConsoleMessageType::STANDARD, Throwable|null $ex = null, bool $count = true) Print "$msg1 $msg2" with prefix and formatting optionally based on $level
 * @method static ConsoleWriter messageOnce(MessageLevel::* $level, string $msg1, string|null $msg2 = null, ConsoleMessageType::* $type = ConsoleMessageType::STANDARD, Throwable|null $ex = null, bool $count = true) Print "$msg1 $msg2" with prefix and formatting optionally based on $level once per run
 * @method static ConsoleWriter print(string $msg, MessageLevel::* $level = MessageLevel::INFO, ConsoleMessageType::* $type = ConsoleMessageType::UNFORMATTED) Print "$msg" to registered targets
 * @method static ConsoleWriter printOut(string $msg, MessageLevel::* $level = MessageLevel::INFO, ConsoleMessageType::* $type = ConsoleMessageType::UNFORMATTED) Print "$msg" to registered STDOUT or STDERR targets
 * @method static ConsoleWriter printStderr(string $msg, MessageLevel::* $level = MessageLevel::INFO, ConsoleMessageType::* $type = ConsoleMessageType::UNFORMATTED) Print "$msg" to STDERR even if no STDERR target is registered
 * @method static ConsoleWriter printStdout(string $msg, MessageLevel::* $level = MessageLevel::INFO, ConsoleMessageType::* $type = ConsoleMessageType::UNFORMATTED) Print "$msg" to STDOUT even if no STDOUT target is registered
 * @method static ConsoleWriter printTty(string $msg, MessageLevel::* $level = MessageLevel::INFO, ConsoleMessageType::* $type = ConsoleMessageType::UNFORMATTED) Print "$msg" to registered TTY targets
 * @method static ConsoleWriter registerStderrTarget() Register STDERR to receive console output if running on the command line (see {@see ConsoleWriter::registerStderrTarget()})
 * @method static ConsoleWriter registerStdioTargets() Register STDOUT and STDERR to receive console output if running on the command line (see {@see ConsoleWriter::registerStdioTargets()})
 * @method static ConsoleWriter registerTarget(Target $target, array<MessageLevel::*> $levels = LevelGroup::ALL) Register a target to receive console output
 * @method static ConsoleWriter setTargetPrefix(string|null $prefix, int-mask-of<ConsoleTargetTypeFlag::*> $flags = 0) Set or unset the prefix applied to each line of output by any registered targets that implement ConsoleTargetPrefixInterface
 * @method static ConsoleWriter summary(string $finishedText = 'Command finished', string $successText = 'without errors', bool $withResourceUsage = false, bool $withoutErrorCount = false, bool $withStandardMessageType = false) Print a "command finished" message with a summary of errors, warnings and resource usage
 * @method static ConsoleWriter warn(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "! $msg1 $msg2" with level WARNING
 * @method static ConsoleWriter warnOnce(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "! $msg1 $msg2" with level WARNING once per run
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
