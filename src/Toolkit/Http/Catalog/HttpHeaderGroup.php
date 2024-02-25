<?php declare(strict_types=1);

namespace Salient\Http\Catalog;

use Salient\Core\AbstractDictionary;

/**
 * Groups of HTTP headers
 *
 * @extends AbstractDictionary<array<HttpHeader::*>>
 */
final class HttpHeaderGroup extends AbstractDictionary
{
    /**
     * @var array<HttpHeader::*>
     */
    public const SENSITIVE = [
        HttpHeader::AUTHORIZATION,
        HttpHeader::PROXY_AUTHORIZATION,
    ];
}
