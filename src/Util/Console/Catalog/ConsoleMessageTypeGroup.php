<?php declare(strict_types=1);

namespace Lkrms\Console\Catalog;

use Lkrms\Concept\Enumeration;
use Lkrms\Console\Catalog\ConsoleMessageType as MessageType;

/**
 * Groups of console message types
 *
 * @api
 *
 * @extends Enumeration<int[]>
 */
final class ConsoleMessageTypeGroup extends Enumeration
{
    public const ALL = [
        MessageType::STANDARD,
        MessageType::UNDECORATED,
        MessageType::UNFORMATTED,
        MessageType::GROUP_START,
        MessageType::GROUP_END,
        MessageType::SUCCESS,
    ];
}
