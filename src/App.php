<?php

declare(strict_types=1);

namespace Lkrms;

/**
 *
 *
 * @package Lkrms
 */
abstract class App
{
    private const CLASS_ALIASES = [
        \Lkrms\Assert::class   => \Lkrms\Util\Assert::class,
        \Lkrms\Cache::class    => \Lkrms\Store\Cache::class,
        \Lkrms\Cli::class      => \Lkrms\Cli\Cli::class,
        \Lkrms\Closure::class  => \Lkrms\Core\ClosureBuilder::class,
        \Lkrms\Console::class  => \Lkrms\Console\Console::class,
        \Lkrms\Convert::class  => \Lkrms\Util\Convert::class,
        \Lkrms\Curler::class   => \Lkrms\Curler\Curler::class,
        \Lkrms\Env::class      => \Lkrms\Util\Env::class,
        \Lkrms\Err::class      => \Lkrms\Err\Err::class,
        \Lkrms\File::class     => \Lkrms\Util\File::class,
        \Lkrms\Format::class   => \Lkrms\Util\Format::class,
        \Lkrms\Generate::class => \Lkrms\Util\Generate::class,
        \Lkrms\Reflect::class  => \Lkrms\Util\Reflect::class,
        \Lkrms\Sql::class      => \Lkrms\Util\Sql::class,
        \Lkrms\Test::class     => \Lkrms\Util\Test::class,
        \Lkrms\Trash::class    => \Lkrms\Store\Trash::class,

        \Lkrms\Console\ConsoleTarget::class        => \Lkrms\Console\ConsoleTarget\ConsoleTarget::class,
        \Lkrms\Console\ConsoleTarget\Analog::class => \Lkrms\Console\ConsoleTarget\AnalogTarget::class,
        \Lkrms\Console\ConsoleTarget\Logger::class => \Lkrms\Console\ConsoleTarget\LoggerTarget::class,
        \Lkrms\Console\ConsoleTarget\Stream::class => \Lkrms\Console\ConsoleTarget\StreamTarget::class,
    ];

    /**
     * @var bool
     */
    private static $LegacyAutoloaderIsRegistered;

    /**
     * Register an autoloader for renamed classes
     *
     * If the class being loaded matches the previous name of a defined class,
     * the autoloader creates an alias that allows the previous name to be used.
     *
     * @return void
     */
    public static function registerLegacyAutoloader(): void
    {
        if (self::$LegacyAutoloaderIsRegistered)
        {
            return;
        }

        spl_autoload_register(function (string $className)
        {
            if ($class = self::CLASS_ALIASES[$className] ?? null)
            {
                class_alias($class, $className);
            }
        });

        self::$LegacyAutoloaderIsRegistered = true;
    }
}
