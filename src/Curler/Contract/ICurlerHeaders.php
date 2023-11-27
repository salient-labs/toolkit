<?php declare(strict_types=1);

namespace Lkrms\Curler\Contract;

use Lkrms\Contract\IImmutable;
use Lkrms\Curler\CurlerHeadersFlag as Flag;
use Lkrms\Http\Auth\AccessToken;
use Lkrms\Http\Catalog\HttpHeader;

/**
 * A collection of HTTP headers
 */
interface ICurlerHeaders extends IImmutable
{
    /**
     * @return $this
     */
    public function addRawHeader(string $line);

    /**
     * @return $this
     */
    public function addHeader(string $name, string $value, bool $private = false);

    /**
     * @param string|null $pattern If set, only remove headers where value
     * matches `$pattern`.
     * @return $this
     */
    public function unsetHeader(string $name, ?string $pattern = null);

    /**
     * @return $this
     */
    public function setHeader(string $name, string $value, bool $private = false);

    /**
     * @return $this
     */
    public function applyAccessToken(AccessToken $token, string $name = HttpHeader::AUTHORIZATION);

    /**
     * @return $this
     */
    public function addPrivateHeaderName(string $name);

    /**
     * True if a header has been set
     *
     * @param string|null $pattern If set, at least one of the header's values
     * must match `$pattern`.
     */
    public function hasHeader(string $name, ?string $pattern = null): bool;

    /**
     * Get all headers in their original order
     *
     * For example:
     *
     * ```php
     * [
     *     'Accept:application/json',
     *     'User-Agent:php/8.2.3',
     * ]
     * ```
     *
     * @return string[]
     */
    public function getHeaders(): array;

    /**
     * Get the value of a header
     *
     * @param int-mask-of<Flag::*> $flags
     * @param string|null $pattern If set, exclude headers where value doesn't
     * match `$pattern`.
     * @return string[]|string|null If {@see Flag::COMBINE},
     * {@see Flag::KEEP_LAST} or {@see Flag::KEEP_FIRST} are set:
     * - a `string` containing one or more comma-separated values, or
     * - `null` if there are no matching headers
     *
     * Otherwise:
     * - a `string[]` containing one or more values, or
     * - an empty `array` if there are no matching headers
     */
    public function getHeaderValue(string $name, int $flags = 0, ?string $pattern = null);

    /**
     * Get the values of all headers
     *
     * @param int-mask-of<Flag::*> $flags
     * @return array<string,string[]|string> An array that maps lowercase header
     * names to values returned by {@see ICurlerHeaders::getHeaderValue()}.
     */
    public function getHeaderValues(int $flags = 0): array;

    /**
     * @return string[]
     */
    public function getPublicHeaders(): array;

    /**
     * @return string[]
     */
    public function getTrailers(): array;
}
