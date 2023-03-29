<?php declare(strict_types=1);

namespace Lkrms\Support\Dictionary;

use Lkrms\Concept\Dictionary;

/**
 * HTTP request methods
 *
 * @see HttpRequestMethods::ALL
 */
final class HttpRequestMethod extends Dictionary
{
    public const GET     = 'GET';
    public const HEAD    = 'HEAD';
    public const POST    = 'POST';
    public const PUT     = 'PUT';
    public const PATCH   = 'PATCH';
    public const DELETE  = 'DELETE';
    public const CONNECT = 'CONNECT';
    public const OPTIONS = 'OPTIONS';
    public const TRACE   = 'TRACE';
}
