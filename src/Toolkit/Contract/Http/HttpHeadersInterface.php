<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Salient\Contract\Collection\CollectionInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\Immutable;

/**
 * @api
 *
 * @extends CollectionInterface<string,string[]>
 */
interface HttpHeadersInterface extends CollectionInterface, Immutable
{
    /**
     * Parse and apply an HTTP header field or continuation thereof
     *
     * This method should be called once per HTTP header line. Each line must
     * have a trailing CRLF. If an empty line (`"\r\n"`) is given, subsequent
     * headers applied via {@see HttpHeadersInterface::addLine()} are flagged as
     * trailers. Aside from {@see HttpHeadersInterface::trailers()} and
     * {@see HttpHeadersInterface::withoutTrailers()},
     * {@see HttpHeadersInterface} methods make no distinction between trailers
     * and other headers.
     *
     * @param bool $strict If `true`, throw an exception if `$line` is not
     * \[RFC7230]-compliant.
     * @return static
     */
    public function addLine(string $line, bool $strict = false);

    /**
     * Apply a value to a header, preserving any existing values
     *
     * @param string $key
     * @param string[]|string $value
     * @return static
     */
    public function add($key, $value);

    /**
     * Apply a value to a header, replacing any existing values
     *
     * @param string[]|string $value
     */
    public function set($key, $value);

    /**
     * Remove a header
     */
    public function unset($key);

    /**
     * Merge the collection with the given headers, optionally preserving
     * existing values
     *
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $items
     */
    public function merge($items, bool $addToExisting = false);

    /**
     * Apply an access token to the collection
     *
     * @return static
     */
    public function authorize(
        AccessTokenInterface $token,
        string $headerName = HttpHeader::AUTHORIZATION
    );

    /**
     * Reduce the collection to headers received after the HTTP message body
     *
     * @return static
     */
    public function trailers();

    /**
     * Reduce the collection to headers received before the HTTP message body
     *
     * @return static
     */
    public function withoutTrailers();

    /**
     * Get header names and values in their original order as a list of HTTP
     * header fields, preserving the original case of each header
     *
     * @return string[]
     */
    public function getLines(string $format = '%s: %s'): array;

    /**
     * Get an array that maps header names to values, preserving the original
     * case of the first appearance of each header
     *
     * @return array<string,string[]>
     */
    public function getHeaders(): array;

    /**
     * Get an array that maps lowercase header names to comma-separated values
     *
     * @return array<string,string>
     */
    public function getHeaderLines(): array;

    /**
     * True if a header is found in the collection
     */
    public function hasHeader(string $name): bool;

    /**
     * Get the value of a header as a list of values
     *
     * @return string[]
     */
    public function getHeader(string $name): array;

    /**
     * Get the value of a header as a comma-separated string
     *
     * @param bool $lastValueOnly If `true` and the header has multiple values,
     * ignore all but the last value applied.
     */
    public function getHeaderLine(string $name, bool $lastValueOnly = false): string;

    /**
     * Get header names and values in their original order as a list of HTTP
     * Archive (HAR) header objects, preserving the original case of each header
     *
     * @return array<array{name:string,value:string}>
     */
    public function jsonSerialize(): array;
}
