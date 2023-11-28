<?php declare(strict_types=1);

namespace Lkrms\Http\Catalog;

use Lkrms\Concept\Dictionary;

/**
 * Groups of HTTP headers
 *
 * @extends Dictionary<array<HttpHeader::*>>
 */
final class HttpHeaderGroup extends Dictionary
{
    /**
     * @var array<HttpHeader::*>
     */
    public const SENSITIVE = [
        HttpHeader::AUTHORIZATION,
        HttpHeader::PROXY_AUTHORIZATION,
    ];
}
