<?php

declare(strict_types=1);

namespace Lkrms\Err;

use Lkrms\Console\Console;
use Whoops\Handler\Handler;
use Whoops\Handler\PlainTextHandler;

/**
 *
 * @package Lkrms
 */
class CliHandler extends PlainTextHandler
{
    public function handle()
    {
        if ($this->getLogger())
        {
            $this->loggerOnly(true);
            parent::handle();
        }

        Console::exception($this->getException());

        return Handler::QUIT;
    }
}
