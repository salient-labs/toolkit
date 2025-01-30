<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Psr\Log\LoggerInterface;
use Salient\Console\ConsoleWriter;
use Salient\Contract\Console\ConsoleFormatterInterface as FormatterInterface;
use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Console\ConsoleTargetInterface;
use Salient\Contract\Console\ConsoleTargetStreamInterface;
use Salient\Contract\Console\ConsoleTargetTypeFlag;
use Salient\Contract\Console\ConsoleWriterInterface;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Core\MessageLevelGroup as LevelGroup;
use Salient\Core\AbstractFacade;
use Throwable;

/**
 * A facade for the global console writer
 *
 * @method static ConsoleWriterInterface clearProgress() Print a "clear to end of line" control sequence with level INFO to TTY targets with a logProgress() message
 * @method static ConsoleWriterInterface count(Level::* $level) Record a message with level $level without printing anything
 * @method static ConsoleWriterInterface debug(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, int $depth = 0) Print ": <caller> $msg1 $msg2" with level DEBUG (see {@see ConsoleWriterInterface::debug()})
 * @method static ConsoleWriterInterface debugOnce(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, int $depth = 0) Print ": <caller> $msg1 $msg2" with level DEBUG once per run (see {@see ConsoleWriterInterface::debugOnce()})
 * @method static ConsoleWriterInterface deregisterTarget(ConsoleTargetInterface $target) Deregister and close a registered target (see {@see ConsoleWriterInterface::deregisterTarget()})
 * @method static ConsoleWriterInterface error(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "! $msg1 $msg2" with level ERROR
 * @method static ConsoleWriterInterface errorOnce(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "! $msg1 $msg2" with level ERROR once per run
 * @method static string escape(string $string) Escape a string so it can be safely used in a console message
 * @method static ConsoleWriterInterface exception(Throwable $exception, Level::* $level = Level::ERROR, Level::*|null $traceLevel = Level::DEBUG) Print an exception's name and message with a given level, optionally followed by its stack trace with a different level (see {@see ConsoleWriterInterface::exception()})
 * @method static int getErrorCount() Get the number of error messages recorded by the writer so far
 * @method static FormatterInterface getFormatter(Level::* $level = Level::INFO) Get an output formatter for a registered target (see {@see ConsoleWriterInterface::getFormatter()})
 * @method static LoggerInterface getLogger() Get a PSR-3 logger backed by the writer
 * @method static ConsoleTargetStreamInterface getStderrTarget() Get a target for STDERR, creating an unregistered one if necessary
 * @method static ConsoleTargetStreamInterface getStdoutTarget() Get a target for STDOUT, creating an unregistered one if necessary
 * @method static ConsoleTargetInterface[] getTargets(Level::*|null $level = null, int-mask-of<ConsoleTargetTypeFlag::*> $flags = 0) Get a list of registered targets, optionally filtered by level and type
 * @method static int getWarningCount() Get the number of warning messages recorded by the writer so far
 * @method static int|null getWidth(Level::* $level = Level::INFO) Get the width of a registered target in columns (see {@see ConsoleWriterInterface::getWidth()})
 * @method static ConsoleWriterInterface group(string $msg1, string|null $msg2 = null, string|null $endMsg1 = null, string|null $endMsg2 = null) Increase the indentation level of messages and print "» $msg1 $msg2" with level NOTICE (see {@see ConsoleWriterInterface::group()})
 * @method static ConsoleWriterInterface groupEnd() Close the nested message group most recently opened with group()
 * @method static ConsoleWriterInterface info(string $msg1, string|null $msg2 = null) Print "➤ $msg1 $msg2" with level NOTICE
 * @method static ConsoleWriterInterface infoOnce(string $msg1, string|null $msg2 = null) Print "➤ $msg1 $msg2" with level NOTICE once per run
 * @method static ConsoleWriterInterface log(string $msg1, string|null $msg2 = null) Print "- $msg1 $msg2" with level INFO
 * @method static ConsoleWriterInterface logOnce(string $msg1, string|null $msg2 = null) Print "- $msg1 $msg2" with level INFO once per run
 * @method static ConsoleWriterInterface logProgress(string $msg1, string|null $msg2 = null) Print "⠿ $msg1 $msg2" with level INFO to TTY targets without moving to the next line (see {@see ConsoleWriterInterface::logProgress()})
 * @method static ConsoleWriterInterface message(string $msg1, string|null $msg2 = null, Level::* $level = Level::INFO, MessageType::* $type = MessageType::UNDECORATED, Throwable|null $ex = null, bool $count = true) Print "$msg1 $msg2" with prefix and formatting optionally based on $level
 * @method static ConsoleWriterInterface messageOnce(string $msg1, string|null $msg2 = null, Level::* $level = Level::INFO, MessageType::* $type = MessageType::UNDECORATED, Throwable|null $ex = null, bool $count = true) Print "$msg1 $msg2" with prefix and formatting optionally based on $level once per run
 * @method static ConsoleWriterInterface print(string $msg, Level::* $level = Level::INFO, MessageType::* $type = MessageType::UNFORMATTED) Print "$msg" to registered targets
 * @method static ConsoleWriterInterface printOut(string $msg, Level::* $level = Level::INFO, MessageType::* $type = MessageType::UNFORMATTED) Print "$msg" to registered STDOUT or STDERR targets
 * @method static ConsoleWriterInterface printStderr(string $msg, Level::* $level = Level::INFO, MessageType::* $type = MessageType::UNFORMATTED) Print "$msg" to STDERR even if no STDERR target is registered
 * @method static ConsoleWriterInterface printStdout(string $msg, Level::* $level = Level::INFO, MessageType::* $type = MessageType::UNFORMATTED) Print "$msg" to STDOUT even if no STDOUT target is registered
 * @method static ConsoleWriterInterface printTty(string $msg, Level::* $level = Level::INFO, MessageType::* $type = MessageType::UNFORMATTED) Print "$msg" to registered TTY targets
 * @method static ConsoleWriterInterface registerStderrTarget() Register STDERR to receive console output if running on the command line (see {@see ConsoleWriterInterface::registerStderrTarget()})
 * @method static ConsoleWriterInterface registerStdioTargets() Register STDOUT and STDERR to receive console output if running on the command line (see {@see ConsoleWriterInterface::registerStdioTargets()})
 * @method static ConsoleWriterInterface registerTarget(ConsoleTargetInterface $target, array<Level::*> $levels = LevelGroup::ALL) Register a target to receive console output
 * @method static ConsoleWriterInterface setTargetPrefix(string|null $prefix, int-mask-of<ConsoleTargetTypeFlag::*> $flags = 0) Set or unset the prefix applied to each line of output by any registered targets that implement ConsoleTargetPrefixInterface
 * @method static ConsoleWriterInterface summary(string $finishedText = 'Command finished', string $successText = 'without errors', bool $withResourceUsage = false, bool $withoutErrorCount = false, bool $withStandardMessageType = false) Print a "command finished" message with a summary of errors, warnings and resource usage
 * @method static ConsoleWriterInterface warn(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "^ $msg1 $msg2" with level WARNING
 * @method static ConsoleWriterInterface warnOnce(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "^ $msg1 $msg2" with level WARNING once per run
 *
 * @api
 *
 * @extends AbstractFacade<ConsoleWriterInterface>
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
        return [
            ConsoleWriterInterface::class,
            ConsoleWriter::class,
        ];
    }
}
