<?php declare(strict_types=1);

namespace Lkrms\Cli\Contract;

use Lkrms\Cli\CliCommand;

/**
 * A runnable CLI command
 *
 * @see CliCommand
 */
interface ICliCommand extends ICliCommandNode
{
    /**
     * Parse the arguments and run the command
     *
     */
    public function __invoke(string ...$args): int;
}
