<?php declare(strict_types=1);

namespace Salient\Contract\Console;

use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Core\AbstractDictionary;

/**
 * Groups of console message types
 *
 * @api
 *
 * @extends AbstractDictionary<list<MessageType::*>>
 */
final class ConsoleMessageTypeGroup extends AbstractDictionary
{
    /**
     * @var list<MessageType::*>
     */
    public const ALL = [
        MessageType::STANDARD,
        MessageType::UNDECORATED,
        MessageType::UNFORMATTED,
        MessageType::GROUP_START,
        MessageType::GROUP_END,
        MessageType::SUCCESS,
    ];
}
