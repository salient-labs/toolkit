<?php declare(strict_types=1);

namespace Lkrms\Console\Support;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleTargetTypeFlag as TargetTypeFlag;
use Lkrms\Console\Contract\ConsoleTargetInterface as Target;
use Lkrms\Console\Contract\ConsoleTargetStreamInterface as TargetStream;
use Lkrms\Console\ConsoleWriter;

/**
 * ConsoleWriter state information
 */
final class ConsoleWriterState
{
    /**
     * @var array<Level::*,TargetStream[]>
     */
    public array $StdioTargetsByLevel = [];

    /**
     * @var array<Level::*,TargetStream[]>
     */
    public array $TtyTargetsByLevel = [];

    /**
     * @var array<Level::*,Target[]>
     */
    public array $TargetsByLevel = [];

    /**
     * @var array<int,Target>
     */
    public array $Targets = [];

    /**
     * @var array<int,Target>
     */
    public array $DeregisteredTargets = [];

    /**
     * @var array<int,int-mask-of<TargetTypeFlag::*>>
     */
    public array $TargetTypeFlags = [];

    public ?TargetStream $StdoutTarget = null;

    public ?TargetStream $StderrTarget = null;

    public int $GroupLevel = -1;

    /**
     * @var string[]
     */
    public array $GroupMessageStack = [];

    public int $Errors = 0;

    public int $Warnings = 0;

    /**
     * @var array<string,true>
     */
    public array $Written = [];
}
