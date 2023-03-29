<?php declare(strict_types=1);

namespace Lkrms\Support\Dictionary;

use Lkrms\Concept\Dictionary;

/**
 * Frequently-used HTTP headers
 *
 */
final class HttpHeader extends Dictionary
{
    public const ACCEPT        = 'Accept';
    public const AUTHORIZATION = 'Authorization';
    public const CONTENT_TYPE  = 'Content-Type';
    public const USER_AGENT    = 'User-Agent';
}
