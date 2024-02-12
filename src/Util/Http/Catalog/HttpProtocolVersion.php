<?php declare(strict_types=1);

namespace Lkrms\Http\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * HTTP protocol versions
 *
 * @extends Enumeration<string>
 */
final class HttpProtocolVersion extends Enumeration
{
    public const HTTP_1_0 = '1.0';

    public const HTTP_1_1 = '1.1';
}
