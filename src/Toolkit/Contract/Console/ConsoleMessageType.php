<?php declare(strict_types=1);

namespace Salient\Contract\Console;

/**
 * Console message types
 *
 * @api
 */
interface ConsoleMessageType
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
     * A message that should be displayed temporarily and should not be recorded
     */
    public const PROGRESS = 3;

    /**
     * The start of a group of console messages
     */
    public const GROUP_START = 4;

    /**
     * The end of a group of console messages
     */
    public const GROUP_END = 5;

    /**
     * "Command finished" or similar
     */
    public const SUMMARY = 6;

    /**
     * "Command finished without errors" or similar
     */
    public const SUCCESS = 7;

    /**
     * "Command finished with errors" or similar
     */
    public const FAILURE = 8;
}
