<?php declare(strict_types=1);

namespace Lkrms\Cli\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Command line option display locations
 *
 * @extends Enumeration<int>
 */
final class CliOptionVisibility extends Enumeration
{
    public const NONE = 0;

    public const SYNOPSIS = 1;

    public const HELP = 2;

    public const COMPLETION = 4;

    public const ALL = CliOptionVisibility::SYNOPSIS | CliOptionVisibility::HELP | CliOptionVisibility::COMPLETION;
}
