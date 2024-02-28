<?php declare(strict_types=1);

namespace Salient\Console\Support;

use Salient\Catalog\Console\ConsoleTargetTypeFlag as TargetTypeFlag;
use Salient\Catalog\Core\MessageLevel as Level;
use Salient\Console\ConsoleWriter;
use Salient\Contract\Console\ConsoleTargetInterface as Target;
use Salient\Contract\Console\ConsoleTargetStreamInterface as TargetStream;

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
