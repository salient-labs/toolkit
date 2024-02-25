<?php declare(strict_types=1);

namespace Salient\Http\Catalog;

use Salient\Core\AbstractDictionary;

/**
 * Groups of HTTP request methods
 *
 * @extends AbstractDictionary<array<HttpRequestMethod::*>>
 */
final class HttpRequestMethodGroup extends AbstractDictionary
{
    /**
     * @var array<HttpRequestMethod::*>
     */
    public const ALL = [
        HttpRequestMethod::GET,
        HttpRequestMethod::HEAD,
        HttpRequestMethod::POST,
        HttpRequestMethod::PUT,
        HttpRequestMethod::PATCH,
        HttpRequestMethod::DELETE,
        HttpRequestMethod::CONNECT,
        HttpRequestMethod::OPTIONS,
        HttpRequestMethod::TRACE,
    ];
}
