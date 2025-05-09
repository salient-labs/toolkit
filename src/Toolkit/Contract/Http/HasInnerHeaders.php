<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Salient\Contract\Core\Immutable;

/**
 * @internal
 */
interface HasInnerHeaders extends HasHeaders, Immutable
{
    /**
     * Get inner headers
     */
    public function getInnerHeaders(): HeadersInterface;

    /**
     * Get an instance with a value applied to a header, replacing any existing
     * values
     *
     * @param string[]|string $value
     * @return static
     */
    public function withHeader(string $name, $value);

    /**
     * Get an instance with a value applied to a header, preserving any existing
     * values
     *
     * @param string[]|string $value
     * @return static
     */
    public function withAddedHeader(string $name, $value);

    /**
     * Get an instance where a header is removed if present
     *
     * @return static
     */
    public function withoutHeader(string $name);
}
