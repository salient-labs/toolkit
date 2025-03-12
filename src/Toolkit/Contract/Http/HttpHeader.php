<?php declare(strict_types=1);

namespace Salient\Contract\Http;

interface HttpHeader
{
    public const ACCEPT = 'Accept';
    public const ACCEPT_ENCODING = 'Accept-Encoding';
    public const AUTHORIZATION = 'Authorization';
    public const CONNECTION = 'Connection';
    public const CONTENT_DISPOSITION = 'Content-Disposition';
    public const CONTENT_LENGTH = 'Content-Length';
    public const CONTENT_TYPE = 'Content-Type';
    public const DATE = 'Date';
    public const HOST = 'Host';
    public const LINK = 'Link';
    public const LOCATION = 'Location';
    public const ODATA_VERSION = 'OData-Version';
    public const PREFER = 'Prefer';
    public const PROXY_AUTHORIZATION = 'Proxy-Authorization';
    public const RETRY_AFTER = 'Retry-After';
    public const SERVER = 'Server';
    public const TRANSFER_ENCODING = 'Transfer-Encoding';
    public const USER_AGENT = 'User-Agent';
}
