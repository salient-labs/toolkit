<?php declare(strict_types=1);

namespace Salient\Contract\Http;

/**
 * @api
 */
interface HasFormDataFlag
{
    public const PRESERVE_LIST_KEYS = 1;
    public const PRESERVE_NUMERIC_KEYS = 2;
    public const PRESERVE_STRING_KEYS = 4;
    public const PRESERVE_ALL_KEYS = 7;
}
