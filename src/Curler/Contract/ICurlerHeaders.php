<?php declare(strict_types=1);

namespace Lkrms\Curler\Contract;

use Lkrms\Contract\IImmutable;
use Lkrms\Curler\CurlerHeadersFlag as Flag;

/**
 * A collection of HTTP headers
 *
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
     * @return $this
     */
    public function unsetHeader(string $name);

    /**
     * @return $this
     */
    public function setHeader(string $name, string $value, bool $private = false);

    /**
     * @return $this
     */
    public function addPrivateHeaderName(string $name);

    /**
     * True if a header has been set
     *
     */
    public function hasHeader(string $name): bool;

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
     * @param int $flags A bitmask of {@see Flag} values.
     * @phpstan-param int-mask-of<Flag::*> $flags
     * @return string[]|string|null If {@see Flag::COMBINE_REPEATED} or
     * {@see Flag::DISCARD_REPEATED} are set:
     * - a `string` containing one or more comma-separated values, or
     * - `null` if there are no matching headers
     *
     * Otherwise:
     * - a `string[]` containing one or more values, or
     * - an empty `array` if there are no matching headers
     */
    public function getHeaderValue(string $name, int $flags = 0);

    /**
     * Get the values of all headers
     *
     * @param int $flags A bitmask of {@see Flag} values.
     * @phpstan-param int-mask-of<Flag::*> $flags
     * @return array<string,string[]|string> An array that maps lowercase header
     * names to values returned by {@see ICurlerHeaders::getHeaderValue()},
     * sorted to maintain the position of each header's last appearance if
     * {@see Flag::SORT_BY_LAST} is set.
     */
    public function getHeaderValues(int $flags = 0): array;

    /**
     * @return string[]
     */
    public function getPublicHeaders(): array;
}
