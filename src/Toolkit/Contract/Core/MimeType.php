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
    public const FORM = 'application/x-www-form-urlencoded';
    public const FORM_MULTIPART = 'multipart/form-data';
    public const GZIP = 'application/gzip';
    public const HTML = 'text/html';
    public const JSON = 'application/json';
    public const JWT = 'application/jwt';
    public const TEXT = 'text/plain';
    public const XML = 'application/xml';
    public const YAML = 'application/yaml';
    public const ZIP = 'application/zip';
}
