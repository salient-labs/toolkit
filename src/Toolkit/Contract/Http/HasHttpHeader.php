<?php declare(strict_types=1);

namespace Salient\Contract\Http;

/**
 * @api
 */
interface HasHttpHeader
{
    public const HEADER_ACCEPT = 'Accept';
    public const HEADER_ACCEPT_ENCODING = 'Accept-Encoding';
    public const HEADER_AUTHORIZATION = 'Authorization';
    public const HEADER_CONNECTION = 'Connection';
    public const HEADER_CONTENT_DISPOSITION = 'Content-Disposition';
    public const HEADER_CONTENT_LENGTH = 'Content-Length';
    public const HEADER_CONTENT_TYPE = 'Content-Type';
    public const HEADER_DATE = 'Date';
    public const HEADER_HOST = 'Host';
    public const HEADER_LINK = 'Link';
    public const HEADER_LOCATION = 'Location';
    public const HEADER_ODATA_VERSION = 'OData-Version';
    public const HEADER_PREFER = 'Prefer';
    public const HEADER_PROXY_AUTHORIZATION = 'Proxy-Authorization';
    public const HEADER_RETRY_AFTER = 'Retry-After';
    public const HEADER_SERVER = 'Server';
    public const HEADER_TRANSFER_ENCODING = 'Transfer-Encoding';
    public const HEADER_USER_AGENT = 'User-Agent';
}
