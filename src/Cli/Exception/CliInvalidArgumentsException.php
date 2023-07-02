<?php declare(strict_types=1);

namespace Lkrms\Cli\Exception;

/**
 * Thrown when one or more invalid command-line arguments are given
 *
 */
class CliInvalidArgumentsException extends \Lkrms\Exception\MultipleErrorException
{
    public function __construct(string ...$errors)
    {
        parent::__construct('Invalid arguments', ...$errors);
    }
}
