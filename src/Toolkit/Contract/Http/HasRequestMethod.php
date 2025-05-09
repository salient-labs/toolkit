<?php declare(strict_types=1);

namespace Salient\Contract\Http;

/**
 * @api
 */
interface HasRequestMethod
{
    public const METHOD_GET = 'GET';
    public const METHOD_HEAD = 'HEAD';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_CONNECT = 'CONNECT';
    public const METHOD_OPTIONS = 'OPTIONS';
    public const METHOD_TRACE = 'TRACE';
}
