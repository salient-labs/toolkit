<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Salient\Core\AbstractDictionary;

/**
 * @api
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
