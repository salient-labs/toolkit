<?php declare(strict_types=1);

namespace Salient\Cli\Exception;

use Salient\Core\Exception\MultipleErrorException;

/**
 * Thrown when invalid command line arguments are given
 */
class CliInvalidArgumentsException extends MultipleErrorException
{
    public function __construct(string ...$errors)
    {
        parent::__construct('Invalid arguments', ...$errors);

        // Must be called after parent::__construct()
        $this->ExitStatus = 1;
    }
}
