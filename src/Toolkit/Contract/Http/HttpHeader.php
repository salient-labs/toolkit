<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Salient\Core\AbstractDictionary;

/**
 * @api
 *
 * @extends AbstractDictionary<string>
 */
final class HttpHeader extends AbstractDictionary
{
    public const ACCEPT = 'Accept';
    public const ACCEPT_ENCODING = 'Accept-Encoding';
    public const AUTHORIZATION = 'Authorization';
    public const CONTENT_DISPOSITION = 'Content-Disposition';
    public const CONTENT_LENGTH = 'Content-Length';
    public const CONTENT_TYPE = 'Content-Type';
    public const DATE = 'Date';
    public const HOST = 'Host';
    public const ODATA_VERSION = 'OData-Version';
    public const PREFER = 'Prefer';
    public const PROXY_AUTHORIZATION = 'Proxy-Authorization';
    public const RETRY_AFTER = 'Retry-After';
    public const SERVER = 'Server';
    public const TRANSFER_ENCODING = 'Transfer-Encoding';
    public const USER_AGENT = 'User-Agent';
}
