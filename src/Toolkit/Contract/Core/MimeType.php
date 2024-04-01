<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use Salient\Core\AbstractDictionary;

/**
 * @api
 *
 * @extends AbstractDictionary<string>
 */
final class MimeType extends AbstractDictionary
{
    public const BINARY = 'application/octet-stream';
    public const FORM_MULTIPART = 'multipart/form-data';
    public const FORM_URLENCODED = 'application/x-www-form-urlencoded';
    public const JSON = 'application/json';
    public const TEXT = 'text/plain';
}
