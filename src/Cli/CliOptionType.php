<?php

declare(strict_types=1);

namespace Lkrms\Cli;

/**
 *
 * @package Lkrms
 */
class CliOptionType
{
    /**
     * e.g. `-v`
     */
    public const FLAG = 0;

    /**
     * e.g. `-v LEVEL`
     */
    public const VALUE = 1;

    /**
     * e.g. `-vLEVEL`
     */
    public const VALUE_OPTIONAL = 2;

    /**
     * e.g. `-v on|off`
     */
    public const ONE_OF = 3;

    /**
     * e.g. `-v(on|off)`
     */
    public const ONE_OF_OPTIONAL = 4;
}

