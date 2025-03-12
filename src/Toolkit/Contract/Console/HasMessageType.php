<?php declare(strict_types=1);

namespace Salient\Contract\Console;

/**
 * Console message types
 */
interface HasMessageType
{
    /**
     * A message that should be recorded with a prefix and level-based
     * formatting
     */
    public const TYPE_STANDARD = 0;

    /**
     * A message that should be recorded without a prefix
     */
    public const TYPE_UNDECORATED = 1;

    /**
     * A message that should be recorded without a prefix or level-based
     * formatting
     */
    public const TYPE_UNFORMATTED = 2;

    /**
     * A message that should be displayed temporarily and should not be recorded
     */
    public const TYPE_PROGRESS = 3;

    /**
     * The start of a group of console messages
     */
    public const TYPE_GROUP_START = 4;

    /**
     * The end of a group of console messages
     */
    public const TYPE_GROUP_END = 5;

    /**
     * "Command finished" or similar
     */
    public const TYPE_SUMMARY = 6;

    /**
     * "Command finished without errors" or similar
     */
    public const TYPE_SUCCESS = 7;

    /**
     * "Command finished with errors" or similar
     */
    public const TYPE_FAILURE = 8;
}
