<?php declare(strict_types=1);

namespace Lkrms\Console\Catalog;

use Lkrms\Concept\Enumeration;
use Lkrms\Console\ConsoleFormatter;
use Lkrms\Console\ConsoleWriter;

/**
 * Console message types
 *
 * Message types are orthogonal to message levels. They may be used to improve
 * the appearance and accessibility of console output and are passed to
 * {@see ConsoleFormatter::formatMessage()} for this purpose. They cannot be
 * used to filter messages delivered to output targets.
 *
 * @extends Enumeration<int>
 */
final class ConsoleMessageType extends Enumeration
{
    /**
     * A message with no explicit type
     *
     */
    public const DEFAULT = 0;

    /**
     * A message with no prefix
     *
     */
    public const UNDECORATED = 1;

    /**
     * A message with no prefix and no formatting based on message level
     *
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
     *
     */
    public const SUCCESS = 5;
}
