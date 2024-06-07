<?php declare(strict_types=1);

namespace Salient\Console\Support;

use Psr\Log\LoggerInterface;
use Salient\Contract\Console\ConsoleTargetInterface as Target;
use Salient\Contract\Console\ConsoleTargetStreamInterface as TargetStream;
use Salient\Contract\Console\ConsoleTargetTypeFlag as TargetTypeFlag;
use Salient\Contract\Core\MessageLevel as Level;

/**
 * ConsoleWriter state information
 */
final class ConsoleWriterState
{
    /** @var array<Level::*,TargetStream[]> */
    public array $StdioTargetsByLevel = [];
    /** @var array<Level::*,TargetStream[]> */
    public array $TtyTargetsByLevel = [];
    /** @var array<Level::*,Target[]> */
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
    /** @var array{int<0,max>,float} */
    public array $SpinnerState = [0, 0.0];
    public LoggerInterface $Logger;
}
