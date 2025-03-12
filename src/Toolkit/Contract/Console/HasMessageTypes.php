<?php declare(strict_types=1);

namespace Salient\Contract\Console;

/**
 * @api
 */
interface HasMessageTypes
{
    public const TYPES_ALL = [
        HasMessageType::TYPE_STANDARD,
        HasMessageType::TYPE_UNDECORATED,
        HasMessageType::TYPE_UNFORMATTED,
        HasMessageType::TYPE_PROGRESS,
        HasMessageType::TYPE_GROUP_START,
        HasMessageType::TYPE_GROUP_END,
        HasMessageType::TYPE_SUMMARY,
        HasMessageType::TYPE_SUCCESS,
        HasMessageType::TYPE_FAILURE,
    ];

    public const TYPES_GROUP = [
        HasMessageType::TYPE_GROUP_START,
        HasMessageType::TYPE_GROUP_END,
    ];
}
