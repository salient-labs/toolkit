<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Salient\Core\AbstractDictionary;

/**
 * HTTP headers
 *
 * @extends AbstractDictionary<string>
 */
final class HttpHeader extends AbstractDictionary
{
    public const ACCEPT = 'Accept';
    public const AUTHORIZATION = 'Authorization';
    public const CONTENT_LENGTH = 'Content-Length';
    public const CONTENT_TYPE = 'Content-Type';
    public const HOST = 'Host';
    public const PREFER = 'Prefer';
    public const PROXY_AUTHORIZATION = 'Proxy-Authorization';
    public const USER_AGENT = 'User-Agent';
}
