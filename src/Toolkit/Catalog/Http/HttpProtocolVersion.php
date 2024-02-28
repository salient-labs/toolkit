<?php declare(strict_types=1);

namespace Salient\Catalog\Http;

use Salient\Core\AbstractEnumeration;

/**
 * HTTP protocol versions
 *
 * @extends AbstractEnumeration<string>
 */
final class HttpProtocolVersion extends AbstractEnumeration
{
    public const HTTP_1_0 = '1.0';

    public const HTTP_1_1 = '1.1';
}
