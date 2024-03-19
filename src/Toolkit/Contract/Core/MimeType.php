<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use Salient\Core\AbstractDictionary;

/**
 * Frequently-used MIME types
 *
 * @extends AbstractDictionary<string>
 */
final class MimeType extends AbstractDictionary
{
    public const TEXT = 'text/plain';
    public const BINARY = 'application/octet-stream';
    public const WWW_FORM = 'application/x-www-form-urlencoded';
    public const JSON = 'application/json';
}
