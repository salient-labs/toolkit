<?php declare(strict_types=1);

namespace Salient\Contract\Console;

use Salient\Contract\Console\ConsoleMessageType as MessageType;

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
        MessageType::STANDARD,
        MessageType::UNDECORATED,
        MessageType::UNFORMATTED,
        MessageType::PROGRESS,
        MessageType::GROUP_START,
        MessageType::GROUP_END,
        MessageType::SUMMARY,
        MessageType::SUCCESS,
        MessageType::FAILURE,
    ];

    /**
     * @var list<MessageType::*>
     */
    public const GROUP = [
        MessageType::GROUP_START,
        MessageType::GROUP_END,
    ];
}
