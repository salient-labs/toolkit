<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Salient\Core\AbstractEnumeration;

/**
 * Form data flags
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class FormDataFlag extends AbstractEnumeration
{
    public const PRESERVE_LIST_KEYS = 1;
    public const PRESERVE_NUMERIC_KEYS = 2;
    public const PRESERVE_STRING_KEYS = 4;

    public const PRESERVE_ALL_KEYS =
        FormDataFlag::PRESERVE_LIST_KEYS
        | FormDataFlag::PRESERVE_NUMERIC_KEYS
        | FormDataFlag::PRESERVE_STRING_KEYS;
}
