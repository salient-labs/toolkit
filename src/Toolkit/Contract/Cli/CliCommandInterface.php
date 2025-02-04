<?php declare(strict_types=1);

namespace Salient\Contract\Cli;

use Salient\Contract\Core\HasJsonSchema;

/**
 * A runnable CLI command
 *
 * @api
 */
interface CliCommandInterface extends CliCommandNodeInterface, HasJsonSchema
{
    /**
     * Parse the given arguments and run the command
     */
    public function __invoke(string ...$args): int;
}
