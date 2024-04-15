<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use Salient\Core\AbstractEnumeration;

/**
 * Json::parse() flags
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class JsonDecodeFlag extends AbstractEnumeration
{
    public const BIGINT_AS_STRING = \JSON_BIGINT_AS_STRING;
    public const INVALID_UTF8_IGNORE = \JSON_INVALID_UTF8_IGNORE;
    public const INVALID_UTF8_SUBSTITUTE = \JSON_INVALID_UTF8_SUBSTITUTE;
    public const OBJECT_AS_ARRAY = \JSON_OBJECT_AS_ARRAY;
}
