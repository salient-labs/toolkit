<?php declare(strict_types=1);

namespace Lkrms\Cli\Exception;

use Lkrms\Exception\MultipleErrorException;

/**
 * Thrown when invalid command line arguments are given
 */
class CliInvalidArgumentsException extends MultipleErrorException
{
    public function __construct(string ...$errors)
    {
        parent::__construct('Invalid arguments', ...$errors);
        $this->ExitStatus = 1;
    }
}
