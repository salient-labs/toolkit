<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * json_decode() flags
 *
 * @extends Enumeration<int>
 */
class JsonDecodeFlag extends Enumeration
{
    public const BIGINT_AS_STRING = \JSON_BIGINT_AS_STRING;
    public const INVALID_UTF8_IGNORE = \JSON_INVALID_UTF8_IGNORE;
    public const INVALID_UTF8_SUBSTITUTE = \JSON_INVALID_UTF8_SUBSTITUTE;
}
