<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Salient\Core\AbstractDictionary;

/**
 * @api
 *
 * @extends AbstractDictionary<array<HttpHeader::*>>
 */
final class HttpHeaderGroup extends AbstractDictionary
{
    /**
     * @var list<HttpHeader::*>
     */
    public const SENSITIVE = [
        HttpHeader::AUTHORIZATION,
        HttpHeader::PROXY_AUTHORIZATION,
    ];
}
