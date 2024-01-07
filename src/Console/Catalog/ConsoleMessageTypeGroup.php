<?php declare(strict_types=1);

namespace Lkrms\Console\Catalog;

use Lkrms\Concept\Enumeration;
use Lkrms\Console\Catalog\ConsoleMessageType as Type;

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
        Type::STANDARD,
        Type::UNDECORATED,
        Type::UNFORMATTED,
        Type::GROUP_START,
        Type::GROUP_END,
        Type::SUCCESS,
    ];
}
