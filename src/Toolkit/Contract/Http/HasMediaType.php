<?php declare(strict_types=1);

namespace Salient\Contract\Http;

/**
 * @api
 */
interface HasMediaType
{
    public const TYPE_BINARY = 'application/octet-stream';
    public const TYPE_FORM = 'application/x-www-form-urlencoded';
    public const TYPE_FORM_MULTIPART = 'multipart/form-data';
    public const TYPE_GZIP = 'application/gzip';
    public const TYPE_HTML = 'text/html';
    public const TYPE_JSON = 'application/json';
    public const TYPE_JWT = 'application/jwt';
    public const TYPE_TEXT = 'text/plain';
    public const TYPE_XML = 'application/xml';
    public const TYPE_YAML = 'application/yaml';
    public const TYPE_ZIP = 'application/zip';
}
