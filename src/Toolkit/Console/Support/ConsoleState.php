<?php declare(strict_types=1);

namespace Salient\Console\Support;

use Psr\Log\LoggerInterface;
use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Contract\Console\ConsoleTargetInterface as Target;
use Salient\Contract\Console\ConsoleTargetStreamInterface as TargetStream;
use Salient\Contract\Console\ConsoleTargetTypeFlag as TargetTypeFlag;

/**
 * Console state information
 */
final class ConsoleState
{
    /** @var array<Console::LEVEL_*,TargetStream[]> */
    public array $StdioTargetsByLevel = [];
    /** @var array<Console::LEVEL_*,TargetStream[]> */
    public array $TtyTargetsByLevel = [];
    /** @var array<Console::LEVEL_*,Target[]> */
    public array $TargetsByLevel = [];
    /** @var array<int,Target> */
    public array $Targets = [];
    /** @var array<int,Target> */
    public array $DeregisteredTargets = [];
    /** @var array<int,int-mask-of<TargetTypeFlag::*>> */
    public array $TargetTypeFlags = [];
    public ?TargetStream $StdoutTarget = null;
    public ?TargetStream $StderrTarget = null;
    public int $GroupLevel = -1;
    /** @var array<array{string|null,string|null}> */
    public array $GroupMessageStack = [];
    public int $ErrorCount = 0;
    public int $WarningCount = 0;
    /** @var array<string,true> */
    public array $Written = [];
    /** @var string[] */
    public array $LastWritten = [];
    /** @var array{int<0,max>,float}|null */
    public ?array $SpinnerState;
    public LoggerInterface $Logger;
}
