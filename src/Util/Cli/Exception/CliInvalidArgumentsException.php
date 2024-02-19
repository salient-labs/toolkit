<?php declare(strict_types=1);

namespace Lkrms\Cli\Exception;

use Salient\Core\AbstractMultipleErrorException;

/**
 * Thrown when invalid command line arguments are given
 *
 * @api
 */
class CliInvalidArgumentsException extends AbstractMultipleErrorException
{
    public function __construct(string ...$errors)
    {
        parent::__construct('Invalid arguments', ...$errors);
        $this->ExitStatus = 1;
    }
}
