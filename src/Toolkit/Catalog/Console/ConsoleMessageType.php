<?php declare(strict_types=1);

namespace Salient\Catalog\Console;

use Salient\Console\ConsoleWriter;
use Salient\Core\AbstractEnumeration;

/**
 * Console message types
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class ConsoleMessageType extends AbstractEnumeration
{
    /**
     * A message that should be recorded with a prefix and level-based
     * formatting
     */
    public const STANDARD = 0;

    /**
     * A message that should be recorded without a prefix
     */
    public const UNDECORATED = 1;

    /**
     * A message that should be recorded without a prefix or level-based
     * formatting
     */
    public const UNFORMATTED = 2;

    /**
     * The start of a group of console messages
     *
     * @see ConsoleWriter::group()
     */
    public const GROUP_START = 3;

    /**
     * The end of a group of console messages
     *
     * @see ConsoleWriter::groupEnd()
     */
    public const GROUP_END = 4;

    /**
     * "Command finished without errors" or similar
     */
    public const SUCCESS = 5;
}
