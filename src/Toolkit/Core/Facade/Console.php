<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Psr\Log\LoggerInterface;
use Salient\Console\Console as ConsoleService;
use Salient\Contract\Console\Format\ConsoleFormatterInterface as FormatterInterface;
use Salient\Contract\Console\Target\StreamTargetInterface;
use Salient\Contract\Console\Target\TargetInterface;
use Salient\Contract\Console\ConsoleInterface;
use Salient\Contract\Console\HasMessageType;
use Salient\Contract\Console\HasMessageTypes;
use Salient\Contract\Console\HasTargetFlag;
use Salient\Contract\HasMessageLevel;
use Salient\Contract\HasMessageLevels;
use Throwable;

/**
 * A facade for the global console service
 *
 * @method static ConsoleInterface clearProgress() Print a "clear to end of line" control sequence with level INFO to TTY targets with a logProgress() message
 * @method static ConsoleInterface count(ConsoleInterface::LEVEL_* $level) Record a message with level $level without printing anything
 * @method static ConsoleInterface debug(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, int $depth = 0) Print ": <caller> $msg1 $msg2" with level DEBUG (see {@see ConsoleInterface::debug()})
 * @method static ConsoleInterface debugOnce(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, int $depth = 0) Print ": <caller> $msg1 $msg2" with level DEBUG once per run (see {@see ConsoleInterface::debugOnce()})
 * @method static ConsoleInterface deregisterTarget(TargetInterface $target) Deregister and close a registered target (see {@see ConsoleInterface::deregisterTarget()})
 * @method static ConsoleInterface error(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "! $msg1 $msg2" with level ERROR
 * @method static ConsoleInterface errorOnce(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "! $msg1 $msg2" with level ERROR once per run
 * @method static string escape(string $string) Escape a string so it can be safely used in a console message
 * @method static ConsoleInterface exception(Throwable $exception, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_ERROR, ConsoleInterface::LEVEL_*|null $traceLevel = ConsoleInterface::LEVEL_DEBUG) Print an exception's name and message with a given level, optionally followed by its stack trace with a different level (see {@see ConsoleInterface::exception()})
 * @method static int getErrorCount() Get the number of error messages recorded so far
 * @method static FormatterInterface getFormatter(ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO) Get an output formatter for a registered target (see {@see ConsoleInterface::getFormatter()})
 * @method static LoggerInterface getLogger() Get a PSR-3 logger
 * @method static StreamTargetInterface getStderrTarget() Get a target for STDERR, creating an unregistered one if necessary
 * @method static StreamTargetInterface getStdoutTarget() Get a target for STDOUT, creating an unregistered one if necessary
 * @method static TargetInterface[] getTargets(ConsoleInterface::LEVEL_*|null $level = null, int-mask-of<ConsoleInterface::TARGET_*> $flags = 0) Get a list of registered targets, optionally filtered by level and type
 * @method static int getWarningCount() Get the number of warning messages recorded so far
 * @method static int|null getWidth(ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO) Get the width of a registered target in columns (see {@see ConsoleInterface::getWidth()})
 * @method static ConsoleInterface group(string $msg1, string|null $msg2 = null, string|null $endMsg1 = null, string|null $endMsg2 = null) Increase the indentation level of messages and print "» $msg1 $msg2" with level NOTICE (see {@see ConsoleInterface::group()})
 * @method static ConsoleInterface groupEnd() Close the nested message group most recently opened with group()
 * @method static ConsoleInterface info(string $msg1, string|null $msg2 = null) Print "➤ $msg1 $msg2" with level NOTICE
 * @method static ConsoleInterface infoOnce(string $msg1, string|null $msg2 = null) Print "➤ $msg1 $msg2" with level NOTICE once per run
 * @method static ConsoleInterface log(string $msg1, string|null $msg2 = null) Print "- $msg1 $msg2" with level INFO
 * @method static ConsoleInterface logOnce(string $msg1, string|null $msg2 = null) Print "- $msg1 $msg2" with level INFO once per run
 * @method static ConsoleInterface logProgress(string $msg1, string|null $msg2 = null) Print "⠿ $msg1 $msg2" with level INFO to TTY targets without moving to the next line (see {@see ConsoleInterface::logProgress()})
 * @method static ConsoleInterface message(string $msg1, string|null $msg2 = null, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO, ConsoleInterface::TYPE_* $type = ConsoleInterface::TYPE_UNDECORATED, Throwable|null $ex = null, bool $count = true) Print "$msg1 $msg2" with prefix and formatting optionally based on $level
 * @method static ConsoleInterface messageOnce(string $msg1, string|null $msg2 = null, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO, ConsoleInterface::TYPE_* $type = ConsoleInterface::TYPE_UNDECORATED, Throwable|null $ex = null, bool $count = true) Print "$msg1 $msg2" with prefix and formatting optionally based on $level once per run
 * @method static ConsoleInterface print(string $msg, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO, ConsoleInterface::TYPE_* $type = ConsoleInterface::TYPE_UNFORMATTED) Print "$msg" to registered targets
 * @method static ConsoleInterface printOut(string $msg, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO, ConsoleInterface::TYPE_* $type = ConsoleInterface::TYPE_UNFORMATTED) Print "$msg" to registered STDOUT or STDERR targets
 * @method static ConsoleInterface printStderr(string $msg, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO, ConsoleInterface::TYPE_* $type = ConsoleInterface::TYPE_UNFORMATTED) Print "$msg" to STDERR even if no STDERR target is registered
 * @method static ConsoleInterface printStdout(string $msg, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO, ConsoleInterface::TYPE_* $type = ConsoleInterface::TYPE_UNFORMATTED) Print "$msg" to STDOUT even if no STDOUT target is registered
 * @method static ConsoleInterface printTty(string $msg, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO, ConsoleInterface::TYPE_* $type = ConsoleInterface::TYPE_UNFORMATTED) Print "$msg" to registered TTY targets
 * @method static ConsoleInterface registerStderrTarget() Register STDERR to receive console output if running on the command line (see {@see ConsoleInterface::registerStderrTarget()})
 * @method static ConsoleInterface registerStdioTargets() Register STDOUT and STDERR to receive console output if running on the command line (see {@see ConsoleInterface::registerStdioTargets()})
 * @method static ConsoleInterface registerTarget(TargetInterface $target, array<ConsoleInterface::LEVEL_*> $levels = ConsoleInterface::LEVELS_ALL) Register a target to receive console output
 * @method static ConsoleInterface setTargetPrefix(string|null $prefix, int-mask-of<ConsoleInterface::TARGET_*> $flags = 0) Set or unset the prefix applied to each line of output by any registered targets that implement HasPrefix
 * @method static ConsoleInterface summary(string $finishedText = 'Command finished', string $successText = 'without errors', bool $withResourceUsage = false, bool $withoutErrorCount = false, bool $withStandardMessageType = false) Print a "command finished" message with a summary of errors, warnings and resource usage
 * @method static ConsoleInterface warn(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "^ $msg1 $msg2" with level WARNING
 * @method static ConsoleInterface warnOnce(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "^ $msg1 $msg2" with level WARNING once per run
 *
 * @api
 *
 * @extends Facade<ConsoleInterface>
 *
 * @generated
 */
final class Console extends Facade implements HasMessageLevel, HasMessageLevels, HasMessageType, HasMessageTypes, HasTargetFlag
{
    /**
     * @internal
     */
    protected static function getService()
    {
        return [
            ConsoleInterface::class,
            ConsoleService::class,
        ];
    }
}
