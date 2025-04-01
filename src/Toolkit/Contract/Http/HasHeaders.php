<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Salient\Contract\Http\Exception\InvalidHeaderException;

/**
 * @internal
 */
interface HasHeaders
{
    /**
     * Get an array that maps header names to values, preserving the original
     * case of the first appearance of each header
     *
     * @return array<string,string[]>
     */
    public function getHeaders(): array;

    /**
     * Check if a header exists
     */
    public function hasHeader(string $name): bool;

    /**
     * Get the value of a header as a list of values
     *
     * @return string[]
     */
    public function getHeader(string $name): array;

    /**
     * Get the value of a header as a string of comma-delimited values
     */
    public function getHeaderLine(string $name): string;

    // --

    /**
     * Get an array that maps lowercase header names to comma-separated values
     *
     * @return array<string,string>
     */
    public function getHeaderLines(): array;

    /**
     * Get the value of a header as a list of values, splitting any
     * comma-separated values
     *
     * @return string[]
     */
    public function getHeaderValues(string $name): array;

    /**
     * Get the first value of a header after splitting any comma-separated
     * values
     */
    public function getFirstHeaderValue(string $name): string;

    /**
     * Get the last value of a header after splitting any comma-separated values
     */
    public function getLastHeaderValue(string $name): string;

    /**
     * Get the only value of a header after splitting any comma-separated values
     *
     * @throws InvalidHeaderException if the header has more than one value.
     */
    public function getOnlyHeaderValue(string $name, bool $orSame = false): string;
}
