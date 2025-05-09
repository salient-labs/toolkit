<?php declare(strict_types=1);

namespace Salient\Contract\Http;

/**
 * @api
 */
interface HasHttpHeaders
{
    public const HEADERS_SENSITIVE = [
        HasHttpHeader::HEADER_AUTHORIZATION,
        HasHttpHeader::HEADER_PROXY_AUTHORIZATION,
    ];

    public const HEADERS_UNSTABLE = [
        HasHttpHeader::HEADER_USER_AGENT,
    ];
}
