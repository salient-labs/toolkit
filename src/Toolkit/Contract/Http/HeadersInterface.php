<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Salient\Contract\Collection\DictionaryInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\Immutable;
use Salient\Contract\Http\Exception\InvalidHeaderException;
use LogicException;
use Stringable;

/**
 * @api
 *
 * @extends DictionaryInterface<string,string[]>
 */
interface HeadersInterface extends
    DictionaryInterface,
    HasHeaders,
    Immutable,
    Stringable,
    HasHttpHeader
{
    /**
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $items
     */
    public function __construct($items = []);

    /**
     * Parse and apply a header field line or continuation thereof
     *
     * To initialise an instance from an HTTP stream or message, call this
     * method once per field line after the request or status line, including
     * the CRLF sequence at the end of each line. After receiving an empty line
     * (`"\r\n"`), {@see hasEmptyLine()} returns `true`, and any headers
     * received via {@see addLine()} are applied as trailers.
     *
     * @return static
     * @throws LogicException if headers have been applied to the instance via
     * another method.
     * @throws InvalidHeaderException if `$line` is invalid.
     */
    public function addLine(string $line);

    /**
     * Check if an empty line has been received via addLine()
     */
    public function hasEmptyLine(): bool;

    /**
     * Check if a line with bad whitespace has been received via addLine()
     */
    public function hasBadWhitespace(): bool;

    /**
     * Check if obsolete line folding has been received via addLine()
     */
    public function hasObsoleteLineFolding(): bool;

    /**
     * Apply a value to a header, preserving any existing values
     *
     * @param string $key
     * @param string[]|string $value
     * @return static
     */
    public function addValue($key, $value);

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
     * Merge the collection with the given headers, optionally preserving any
     * existing values
     *
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $items
     */
    public function merge($items, bool $preserveValues = false);

    /**
     * Apply a credential to a header
     *
     * @return static
     */
    public function authorize(
        CredentialInterface $credential,
        string $headerName = HeadersInterface::HEADER_AUTHORIZATION
    );

    /**
     * Move the "Host" header to the start of the collection if present
     *
     * @return static
     */
    public function normalise();

    /**
     * Reduce the collection to headers received after the message body
     *
     * @return static
     */
    public function trailers();

    /**
     * Reduce the collection to headers received before the message body
     *
     * @return static
     */
    public function withoutTrailers();

    /**
     * Get header names and values in their original order as a list of field
     * lines, preserving the original case of each header
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
