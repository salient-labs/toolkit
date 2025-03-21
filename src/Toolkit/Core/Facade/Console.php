<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Psr\Log\LoggerInterface;
use Salient\Console\Console as ConsoleService;
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
 * @method static ConsoleInterface clearProgress() Clear the message most recently printed with logProgress()
 * @method static ConsoleInterface count(ConsoleInterface::LEVEL_* $level) Record a message with the given level without printing anything
 * @method static ConsoleInterface debug(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, int $depth = 0) Print ": <caller> $msg1 $msg2" or similar with level LEVEL_DEBUG (see {@see ConsoleInterface::debug()})
 * @method static ConsoleInterface debugOnce(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, int $depth = 0) Print ": <caller> $msg1 $msg2" or similar with level LEVEL_DEBUG once per run (see {@see ConsoleInterface::debugOnce()})
 * @method static ConsoleInterface deregisterTarget(TargetInterface $target) Deregister a target if it is currently registered with the console
 * @method static ConsoleInterface error(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "! $msg1 $msg2" or similar with level LEVEL_ERROR (see {@see ConsoleInterface::error()})
 * @method static ConsoleInterface errorOnce(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "! $msg1 $msg2" or similar with level LEVEL_ERROR once per run (see {@see ConsoleInterface::errorOnce()})
 * @method static int errors() Get the number of error messages recorded so far
 * @method static string escape(string $string, bool $escapeNewlines = false) Escape inline formatting tags in a string so it can be safely used in a console message
 * @method static ConsoleInterface exception(Throwable $exception, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_ERROR, ConsoleInterface::LEVEL_*|null $traceLevel = ConsoleInterface::LEVEL_DEBUG, bool $count = true) Print an exception and its stack trace with the given message levels (see {@see ConsoleInterface::exception()})
 * @method static StreamTargetInterface getStderrTarget() Get a target for STDERR, creating an unregistered one if necessary
 * @method static StreamTargetInterface getStdoutTarget() Get a target for STDOUT, creating an unregistered one if necessary
 * @method static TargetInterface[] getTargets(ConsoleInterface::LEVEL_*|null $level = null, int-mask-of<ConsoleInterface::TARGET_*> $targetFlags = 0) Get registered targets, optionally filtering them by message level and flag
 * @method static StreamTargetInterface getTtyTarget() Get a TTY target, creating an unregistered one if necessary (see {@see ConsoleInterface::getTtyTarget()})
 * @method static ConsoleInterface group(string $msg1, string|null $msg2 = null, string|null $endMsg1 = null, string|null $endMsg2 = null) Increase message indentation level and print "» $msg1 $msg2" or similar with level LEVEL_NOTICE (see {@see ConsoleInterface::group()})
 * @method static ConsoleInterface groupEnd() Close the group of messages most recently opened with group()
 * @method static ConsoleInterface info(string $msg1, string|null $msg2 = null) Print "➤ $msg1 $msg2" or similar with level LEVEL_NOTICE (see {@see ConsoleInterface::info()})
 * @method static ConsoleInterface infoOnce(string $msg1, string|null $msg2 = null) Print "➤ $msg1 $msg2" or similar with level LEVEL_NOTICE once per run (see {@see ConsoleInterface::infoOnce()})
 * @method static ConsoleInterface log(string $msg1, string|null $msg2 = null) Print "- $msg1 $msg2" or similar with level LEVEL_INFO (see {@see ConsoleInterface::log()})
 * @method static ConsoleInterface logOnce(string $msg1, string|null $msg2 = null) Print "- $msg1 $msg2" or similar with level LEVEL_INFO once per run (see {@see ConsoleInterface::logOnce()})
 * @method static ConsoleInterface logProgress(string $msg1, string|null $msg2 = null) Print "⠋ $msg1 $msg2" or similar with level LEVEL_INFO to TTY targets without moving to the next line (see {@see ConsoleInterface::logProgress()})
 * @method static LoggerInterface logger() Get a PSR-3 logger backed by the console
 * @method static ConsoleInterface message(string $msg1, string|null $msg2 = null, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO, ConsoleInterface::TYPE_* $type = ConsoleInterface::TYPE_UNDECORATED, Throwable|null $ex = null, bool $count = true) Print "$msg1 $msg2" or similar with level- and type-based formatting (see {@see ConsoleInterface::message()})
 * @method static ConsoleInterface messageOnce(string $msg1, string|null $msg2 = null, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO, ConsoleInterface::TYPE_* $type = ConsoleInterface::TYPE_UNDECORATED, Throwable|null $ex = null, bool $count = true) Print "$msg1 $msg2" or similar with level- and type-based formatting once per run (see {@see ConsoleInterface::messageOnce()})
 * @method static ConsoleInterface print(string $msg, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO) Print "$msg" to registered targets (see {@see ConsoleInterface::print()})
 * @method static ConsoleInterface printStdio(string $msg, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO) Print "$msg" to registered targets that write to STDOUT or STDERR (see {@see ConsoleInterface::printStdio()})
 * @method static ConsoleInterface printStdout(string $msg, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO) Print "$msg" to STDOUT, creating an unregistered target if necessary (see {@see ConsoleInterface::printStdout()})
 * @method static ConsoleInterface printTty(string $msg, ConsoleInterface::LEVEL_* $level = ConsoleInterface::LEVEL_INFO) Print "$msg" to registered targets that write to a TTY (see {@see ConsoleInterface::printTty()})
 * @method static ConsoleInterface registerStderrTarget(bool|null $debug = null) Register STDERR for console output if running on the command line (see {@see ConsoleInterface::registerStderrTarget()})
 * @method static ConsoleInterface registerStdioTargets(bool|null $debug = null) Register STDOUT and STDERR for console output if running on the command line (see {@see ConsoleInterface::registerStdioTargets()})
 * @method static ConsoleInterface registerTarget(TargetInterface $target, array<ConsoleInterface::LEVEL_*> $levels = ConsoleInterface::LEVELS_ALL) Register a target to receive output with each of the given message levels from the console
 * @method static ConsoleInterface setPrefix(string|null $prefix, int-mask-of<ConsoleInterface::TARGET_*> $targetFlags = 0) Set or unset the prefix applied to each line of output by registered targets that implement HasPrefix after optionally filtering them by flag
 * @method static ConsoleInterface summary(string $finishedText = 'Command finished', string $successText = 'without errors', bool $withResourceUsage = false, bool $withoutErrorsAndWarnings = false, bool $withGenericType = false) Print a "command finished" message with a summary of errors, warnings and resource usage (see {@see ConsoleInterface::summary()})
 * @method static ConsoleInterface warn(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "^ $msg1 $msg2" or similar with level LEVEL_WARNING (see {@see ConsoleInterface::warn()})
 * @method static ConsoleInterface warnOnce(string $msg1, string|null $msg2 = null, Throwable|null $ex = null, bool $count = true) Print "^ $msg1 $msg2" or similar with level LEVEL_WARNING once per run (see {@see ConsoleInterface::warnOnce()})
 * @method static int warnings() Get the number of warning messages recorded so far
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
