<?php declare(strict_types=1);

namespace Salient\Catalog\Console;

use Salient\Catalog\Console\ConsoleMessageType as MessageType;
use Salient\Core\AbstractEnumeration;

/**
 * Groups of console message types
 *
 * @api
 *
 * @extends AbstractEnumeration<int[]>
 */
final class ConsoleMessageTypeGroup extends AbstractEnumeration
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
