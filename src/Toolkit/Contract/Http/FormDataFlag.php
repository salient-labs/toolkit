<?php declare(strict_types=1);

namespace Salient\Contract\Http;

/**
 * Form data flags
 *
 * @api
 */
interface FormDataFlag
{
    public const PRESERVE_LIST_KEYS = 1;
    public const PRESERVE_NUMERIC_KEYS = 2;
    public const PRESERVE_STRING_KEYS = 4;

    public const PRESERVE_ALL_KEYS =
        FormDataFlag::PRESERVE_LIST_KEYS
        | FormDataFlag::PRESERVE_NUMERIC_KEYS
        | FormDataFlag::PRESERVE_STRING_KEYS;
}
