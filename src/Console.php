<?php

declare(strict_types=1);

namespace Lkrms;

/**
 * Functions for console output
 *
 * @package Lkrms
 */
class Console
{
    public static function Log(string $message, string $message2 = null)
    {
        fwrite(STDERR, $message . (is_null($message2) ? "" : " " . $message2) . "\n");
    }

    public static function Info(string $message, string $message2 = null)
    {
        self::Log($message, $message2);
    }

    public static function Warn(string $message, string $message2 = null)
    {
        self::Log($message, $message2);
    }

    public static function Error(string $message, string $message2 = null)
    {
        self::Log($message, $message2);
    }
}

