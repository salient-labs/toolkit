<?php declare(strict_types=1);

namespace Lkrms\Support\Catalog;

use Lkrms\Concept\Dictionary;

/**
 * Frequently-used HTTP headers
 *
 * @extends Dictionary<string>
 */
final class HttpHeader extends Dictionary
{
    public const ACCEPT = 'Accept';
    public const AUTHORIZATION = 'Authorization';
    public const CONTENT_TYPE = 'Content-Type';
    public const PREFER = 'Prefer';
    public const USER_AGENT = 'User-Agent';
}
