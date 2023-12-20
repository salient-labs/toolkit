<?php declare(strict_types=1);

namespace Lkrms\Http\Catalog;

use Lkrms\Concept\Dictionary;

/**
 * HTTP headers
 *
 * @extends Dictionary<string>
 */
final class HttpHeader extends Dictionary
{
    public const ACCEPT = 'Accept';

    public const AUTHORIZATION = 'Authorization';

    public const CONTENT_TYPE = 'Content-Type';

    public const HOST = 'Host';

    public const PREFER = 'Prefer';

    public const PROXY_AUTHORIZATION = 'Proxy-Authorization';

    public const USER_AGENT = 'User-Agent';
}
