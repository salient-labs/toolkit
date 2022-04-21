<?php

declare(strict_types=1);

namespace Lkrms;

if (!class_alias("\Lkrms\Cli\Cli", "\Lkrms\Cli"))
{
    /**
     * @ignore
     */
    abstract class Cli extends Cli\Cli
    {
    }
}
