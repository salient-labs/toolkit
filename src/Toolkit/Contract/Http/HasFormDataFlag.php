<?php declare(strict_types=1);

namespace Salient\Contract\Http;

/**
 * @api
 */
interface HasFormDataFlag
{
    public const DATA_PRESERVE_LIST_KEYS = 1;
    public const DATA_PRESERVE_NUMERIC_KEYS = 2;
    public const DATA_PRESERVE_STRING_KEYS = 4;
    public const DATA_PRESERVE_ALL_KEYS = 7;
}
