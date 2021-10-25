<?php

declare(strict_types=1);

namespace Lkrms;

use Whoops\Handler\HandlerInterface;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * Basically a wrapper for Whoops
 *
 * @package Lkrms
 */
class Error
{
    /**
     * @var Run
     */
    private static $Whoops;

    /**
     * @param callable|HandlerInterface $handler
     * @return Run
     */
    public static function HandleErrors($handler = null): Run
    {
        if ( ! self::$Whoops)
        {
            self::$Whoops = new Run();
        }

        if ($handler)
        {
            self::$Whoops->pushHandler($handler);
        }

        if (empty(self::$Whoops->getHandlers($handler)))
        {
            if (PHP_SAPI == "cli")
            {
                self::$Whoops->pushHandler(new PlainTextHandler());
            }
            else
            {
                self::$Whoops->pushHandler(new PrettyPageHandler());
            }
        }

        self::$Whoops->register();

        return self::$Whoops;
    }
}

