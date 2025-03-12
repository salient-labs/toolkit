<?php declare(strict_types=1);

namespace Salient\Contract\Http;

interface HttpHeaderGroup
{
    /**
     * @var list<HttpHeader::*>
     */
    public const SENSITIVE = [
        HttpHeader::AUTHORIZATION,
        HttpHeader::PROXY_AUTHORIZATION,
    ];

    /**
     * @var list<HttpHeader::*>
     */
    public const UNSTABLE = [
        HttpHeader::USER_AGENT,
    ];
}
