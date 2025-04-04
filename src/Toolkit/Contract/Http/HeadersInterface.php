<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Salient\Contract\Collection\CollectionInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\Immutable;
use Stringable;

/**
 * @extends CollectionInterface<string,string[]>
 */
interface HeadersInterface extends
    CollectionInterface,
    HasHeaders,
    Immutable,
    Stringable,
    HasHeader
{
    /**
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $items
     */
    public function __construct($items = []);

    /**
     * Parse and apply an HTTP header field or continuation thereof
     *
     * This method should be called once per HTTP header line. Each line must
     * have a trailing CRLF. If an empty line (`"\r\n"`) is given,
     * {@see hasLastLine()} returns `true` and subsequent headers applied via
     * {@see addLine()} are flagged as trailers. Methods other than
     * {@see trailers()} and {@see withoutTrailers()} make no distinction
     * between trailers and other headers.
     *
     * @param bool $strict If `true`, throw an exception if `$line` is not
     * \[RFC7230]-compliant.
     * @return static
     */
    public function addLine(string $line, bool $strict = false);

    /**
     * Check if addLine() has received an empty line
     */
    public function hasLastLine(): bool;

    /**
     * Apply a value to a header, preserving any existing values
     *
     * @param string $key
     * @param string[]|string $value
     * @return static
     */
    public function append($key, $value);

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
        CredentialInterface $credential,
        string $headerName = HeadersInterface::HEADER_AUTHORIZATION
    );

    /**
     * Sort headers in the collection if necessary for compliance with [RFC7230]
     *
     * @return static
     */
    public function canonicalize();

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
     * If `$emptyFormat` is given, it is used for headers with an empty value.
     *
     * @return string[]
     */
    public function getLines(
        string $format = '%s: %s',
        ?string $emptyFormat = null
    ): array;

    /**
     * Get header names and values in their original order as a list of HTTP
     * Archive (HAR) header objects, preserving the original case of each header
     *
     * @return array<array{name:string,value:string}>
     */
    public function jsonSerialize(): array;
}
