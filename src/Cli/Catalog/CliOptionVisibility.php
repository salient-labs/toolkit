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

    public const MARKDOWN = 4;

    public const MAN_PAGE = 8;

    public const COMPLETION = 16;

    public const ALL = CliOptionVisibility::SYNOPSIS | CliOptionVisibility::HELP | CliOptionVisibility::MARKDOWN | CliOptionVisibility::MAN_PAGE | CliOptionVisibility::COMPLETION;
}
