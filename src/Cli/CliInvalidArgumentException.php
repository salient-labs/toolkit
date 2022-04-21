<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Console\Console;

/**
 * Thrown when invalid arguments are given
 *
 * @package Lkrms
 */
class CliInvalidArgumentException extends \Lkrms\Exception
{
    public function __construct(string $message = null)
    {
        if ($message)
        {
            Console::error(Cli::getProgramName() . ": $message");
        }

        parent::__construct($message ?: "Invalid arguments");
    }
}
