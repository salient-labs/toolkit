<?php declare(strict_types=1);

namespace Lkrms\Cli\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Command line option value types
 *
 */
final class CliOptionValueType extends Enumeration
{
    public const BOOLEAN = 0;

    public const INTEGER = 1;

    public const STRING = 2;

    public const DATE = 3;

    public const PATH = 4;

    public const FILE = 5;

    public const DIRECTORY = 6;
}
