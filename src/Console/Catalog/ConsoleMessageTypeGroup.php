<?php declare(strict_types=1);

namespace Lkrms\Console\Catalog;

use Lkrms\Concept\Enumeration;
use Lkrms\Console\Catalog\ConsoleMessageType as Type;

/**
 * Groups of console message types
 *
 * @extends Enumeration<int[]>
 */
final class ConsoleMessageTypes extends Enumeration
{
    public const ALL = [
        Type::DEFAULT,
        Type::UNDECORATED,
        Type::UNFORMATTED,
        Type::GROUP_START,
        Type::GROUP_END,
        Type::SUCCESS,
    ];
}
