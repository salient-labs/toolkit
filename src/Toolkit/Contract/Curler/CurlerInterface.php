<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Http\AccessTokenInterface;
use Salient\Contract\Http\HttpHeader;
use Salient\Contract\Http\HttpHeaderGroup;
use Salient\Contract\Http\HttpHeadersInterface;

interface CurlerInterface
{
    /**
     * Get the URI of the endpoint
     */
    public function getUri(): UriInterface;

    /**
     * Get the last request sent to the endpoint
     */
    public function getLastRequest(): ?RequestInterface;

    /**
     * Get the last response received from the endpoint
     */
    public function getLastResponse(): ?ResponseInterface;

    // --

    /**
     * Send a HEAD request to the endpoint
     *
     * @param mixed[]|null $query
     */
    public function head(?array $query = null): HttpHeadersInterface;

    /**
     * Send a GET request to the endpoint and return the body of the response
     *
     * @param mixed[]|null $query
     * @return mixed
     */
    public function get(?array $query = null);

    /**
     * Send a POST request to the endpoint and return the body of the response
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    public function post($data = null, ?array $query = null);

    /**
     * Send a PUT request to the endpoint and return the body of the response
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    public function put($data = null, ?array $query = null);

    /**
     * Send a PATCH request to the endpoint and return the body of the response
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    public function patch($data = null, ?array $query = null);

    /**
     * Send a DELETE request to the endpoint and return the body of the response
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    public function delete($data = null, ?array $query = null);

    // --

    /**
     * Send a GET request to the endpoint and iterate over response pages
     *
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function getP(?array $query = null): iterable;

    /**
     * Send a POST request to the endpoint and iterate over response pages
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function postP($data = null, ?array $query = null): iterable;

    /**
     * Send a PUT request to the endpoint and iterate over response pages
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function putP($data = null, ?array $query = null): iterable;

    /**
     * Send a PATCH request to the endpoint and iterate over response pages
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function patchP($data = null, ?array $query = null): iterable;

    /**
     * Send a DELETE request to the endpoint and iterate over response pages
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function deleteP($data = null, ?array $query = null): iterable;

    // --

    /**
     * Send raw data to the endpoint in a POST request and return the body of
     * the response
     *
     * @param mixed[]|null $query
     * @return mixed
     */
    public function postR(string $data, string $mimeType, ?array $query = null);

    /**
     * Send raw data to the endpoint in a PUT request and return the body of the
     * response
     *
     * @param mixed[]|null $query
     * @return mixed
     */
    public function putR(string $data, string $mimeType, ?array $query = null);

    /**
     * Send raw data to the endpoint in a PATCH request and return the body of
     * the response
     *
     * @param mixed[]|null $query
     * @return mixed
     */
    public function patchR(string $data, string $mimeType, ?array $query = null);

    /**
     * Send raw data to the endpoint in a DELETE request and return the body of
     * the response
     *
     * @param mixed[]|null $query
     * @return mixed
     */
    public function deleteR(string $data, string $mimeType, ?array $query = null);

    // --

    /**
     * Get request headers
     */
    public function getHeaders(): HttpHeadersInterface;

    /**
     * Get request headers that are not considered sensitive
     */
    public function getPublicHeaders(): HttpHeadersInterface;

    /**
     * Get an instance with the given request headers
     *
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $headers
     * @return static
     */
    public function withHeaders($headers);

    /**
     * Get an instance where the given headers are merged with existing request
     * headers
     *
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $headers
     * @return static
     */
    public function withMergedHeaders($headers, bool $addToExisting = false);

    /**
     * Get an instance with a value applied to a request header, replacing any
     * existing values
     *
     * @param string[]|string $value
     * @return static
     */
    public function withHeader(string $name, $value);

    /**
     * Get an instance with a value applied to a request header, preserving any
     * existing values
     *
     * @param string[]|string $value
     * @return static
     */
    public function withAddedHeader(string $name, $value);

    /**
     * Get an instance with a request header removed
     *
     * @return static
     */
    public function withoutHeader(string $name);

    /**
     * Get an instance that applies an access token to request headers
     *
     * @return static
     */
    public function withAccessToken(
        AccessTokenInterface $token,
        string $headerName = HttpHeader::AUTHORIZATION
    );

    /**
     * Get an instance that does not apply an access token to request headers
     *
     * @return static
     */
    public function withoutAccessToken();

    /**
     * Get an instance that treats a header as sensitive
     *
     * Headers in {@see HttpHeaderGroup::SENSITIVE} are treated as sensitive by
     * default.
     *
     * @return static
     */
    public function withSensitiveHeader(string $name);

    /**
     * Get an instance that does not treat a header as sensitive
     *
     * @return static
     */
    public function withoutSensitiveHeader(string $name);

    /**
     * Get an instance with the given content type applied to request headers
     *
     * If `$mimeType` is `null`, `Content-Type` headers are automatically
     * applied to requests as needed. This is the default behaviour.
     *
     * @return static
     */
    public function withContentType(?string $mimeType);

    /**
     * Get an instance with a given pagination handler
     *
     * @return static
     */
    public function withPager(?CurlerPagerInterface $pager);
}
