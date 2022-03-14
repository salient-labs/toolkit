<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Exception;
use Lkrms\Console\Console;

/**
 * Thrown when invalid arguments are given
 *
 * @package Lkrms
 */
class CliInvalidArgumentException extends Exception
{
    public function __construct(string $message = null)
    {
        if ($message)
        {
            Console::Error(Cli::GetProgramName() . ": $message");
        }

        parent::__construct($message ?: "Invalid arguments");
    }
}

