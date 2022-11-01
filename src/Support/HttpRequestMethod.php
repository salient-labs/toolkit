<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concept\Enumeration;

/**
 * HTTP request methods
 *
 */
final class HttpRequestMethod extends Enumeration
{
    public const GET     = "GET";
    public const HEAD    = "HEAD";
    public const POST    = "POST";
    public const PUT     = "PUT";
    public const PATCH   = "PATCH";
    public const DELETE  = "DELETE";
    public const CONNECT = "CONNECT";
    public const OPTIONS = "OPTIONS";
    public const TRACE   = "TRACE";

    /**
     * @return string[]
     */
    public static function getAll(): array
    {
        return [
            self::GET,
            self::HEAD,
            self::POST,
            self::PUT,
            self::PATCH,
            self::DELETE,
            self::CONNECT,
            self::OPTIONS,
            self::TRACE,
        ];
    }
}
