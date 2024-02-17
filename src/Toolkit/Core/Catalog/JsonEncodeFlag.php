<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * json_encode() flags
 *
 * @extends Enumeration<int>
 */
class JsonEncodeFlag extends Enumeration
{
    public const FORCE_OBJECT = \JSON_FORCE_OBJECT;
    public const INVALID_UTF8_IGNORE = \JSON_INVALID_UTF8_IGNORE;
    public const INVALID_UTF8_SUBSTITUTE = \JSON_INVALID_UTF8_SUBSTITUTE;
    public const NUMERIC_CHECK = \JSON_NUMERIC_CHECK;
    public const PRESERVE_ZERO_FRACTION = \JSON_PRESERVE_ZERO_FRACTION;
    public const UNESCAPED_SLASHES = \JSON_UNESCAPED_SLASHES;
    public const UNESCAPED_UNICODE = \JSON_UNESCAPED_UNICODE;
    public const THROW_ON_ERROR = \JSON_THROW_ON_ERROR;
}
