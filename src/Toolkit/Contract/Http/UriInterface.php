<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Core\Immutable;
use JsonSerializable;
use Stringable;

/**
 * @api
 */
interface UriInterface extends
    PsrUriInterface,
    Stringable,
    JsonSerializable,
    Immutable
{
    /**
     * Parse a URI into its components
     *
     * This method should be used instead of {@see parse_url()} in scenarios
     * where using multiple URI parsers could introduce inconsistent behaviour
     * or security vulnerabilities.
     *
     * @link https://claroty.com/team82/research/white-papers/exploiting-url-parsing-confusion
     * @link https://daniel.haxx.se/blog/2022/01/10/dont-mix-url-parsers/
     *
     * @template T of \PHP_URL_SCHEME|\PHP_URL_HOST|\PHP_URL_PORT|\PHP_URL_USER|\PHP_URL_PASS|\PHP_URL_PATH|\PHP_URL_QUERY|\PHP_URL_FRAGMENT|-1|null
     *
     * @param T $component
     * @return (
     *     T is -1|null
     *     ? array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string}|false
     *     : (T is \PHP_URL_PORT
     *         ? int|null|false
     *         : string|null|false
     *     )
     * )
     */
    public static function parse(string $uri, ?int $component = null);

    /**
     * Get the URI as an array of components
     *
     * Components not present in the URI are not returned.
     *
     * @return array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string}
     */
    public function toParts(): array;

    /**
     * Check if the URI is a relative reference
     */
    public function isReference(): bool;

    /**
     * Get a normalised instance
     *
     * Removes "/./" and "/../" segments from the path. \[RFC3986]-compliant
     * scheme- and protocol-based normalisation may also be performed.
     *
     * Scheme, host and percent-encoded octets in the URI are not normalised by
     * this method because they are always normalised.
     *
     * @return static
     */
    public function normalise(): UriInterface;

    /**
     * Resolve a URI reference to a target URI with the instance as its base URI
     *
     * Implements \[RFC3986] Section 5.2.2 ("Transform References").
     *
     * @param PsrUriInterface|Stringable|string $reference
     * @return static
     */
    public function follow($reference): UriInterface;

    /**
     * Get the string representation of the URI
     */
    public function jsonSerialize(): string;
}
