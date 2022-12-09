<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concept\Enumeration;

/**
 * Frequently-used MIME types
 *
 */
final class MimeType extends Enumeration
{
    public const TEXT     = 'text/plain';
    public const BINARY   = 'application/octet-stream';
    public const WWW_FORM = 'application/x-www-form-urlencoded';
    public const JSON     = 'application/json';
}
