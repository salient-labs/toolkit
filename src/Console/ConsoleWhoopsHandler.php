<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Whoops\Handler\Handler;
use Whoops\Handler\PlainTextHandler;

/**
 *
 * @package Lkrms\Console
 */
class ConsoleWhoopsHandler extends PlainTextHandler
{
    public function handle()
    {
        if ($this->getLogger())
        {
            $response = $this->generateResponse();
            $this->getLogger()->error($response);
        }

        $ex  = $exception = $this->getException();
        $i   = 0;
        $msg = "";

        do
        {
            $msg .= (($i ? "\nCaused by __" . get_class($ex) . "__: " : "") .
                sprintf("__[[__%s__]]__,, in %s:%d,,", $ex->getMessage(), $ex->getFile(), $ex->getLine()));
            $ex = $ex->getPrevious();
            $i++;
        }
        while ($ex);

        Console::Error("Uncaught __" . get_class($exception) . "__:", $msg, $exception);
        Console::Debug("Stack trace:", "__[[__" . $exception->getTraceAsString() . "__]]__");

        return Handler::QUIT;
    }
}

