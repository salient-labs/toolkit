<?php

declare(strict_types=1);

(function ()
{
    $aliases = [
        \Lkrms\Cli::class     => \Lkrms\Cli\Cli::class,
        \Lkrms\Console::class => \Lkrms\Console\Console::class,
        \Lkrms\Curler::class  => \Lkrms\Curler\Curler::class,
        \Lkrms\Err::class     => \Lkrms\Err\Err::class,
    ];

    spl_autoload_register(function ($alias) use ($aliases)
    {
        if ($class = $aliases[$alias] ?? null)
        {
            class_alias($class, $alias);
        }
    });
})();
