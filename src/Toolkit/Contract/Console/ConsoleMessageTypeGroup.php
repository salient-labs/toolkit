<?php declare(strict_types=1);

namespace Salient\Contract\Console;

use Salient\Contract\Console\HasMessageType as MessageType;

/**
 * Groups of console message types
 *
 * @api
 */
interface ConsoleMessageTypeGroup
{
    /**
     * @var list<MessageType::*>
     */
    public const ALL = [
        MessageType::TYPE_STANDARD,
        MessageType::TYPE_UNDECORATED,
        MessageType::TYPE_UNFORMATTED,
        MessageType::TYPE_PROGRESS,
        MessageType::TYPE_GROUP_START,
        MessageType::TYPE_GROUP_END,
        MessageType::TYPE_SUMMARY,
        MessageType::TYPE_SUCCESS,
        MessageType::TYPE_FAILURE,
    ];

    /**
     * @var list<MessageType::*>
     */
    public const GROUP = [
        MessageType::TYPE_GROUP_START,
        MessageType::TYPE_GROUP_END,
    ];
}
