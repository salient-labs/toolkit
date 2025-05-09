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
     * Parse a URI and return its components
     *
     * This method replaces and must be used instead of {@see parse_url()} to
     * mitigate risks arising from the use of multiple URI parsers, which
     * include inconsistent behaviours and security vulnerabilities.
     *
     * @link https://claroty.com/team82/research/white-papers/exploiting-url-parsing-confusion
     * @link https://daniel.haxx.se/blog/2022/01/10/dont-mix-url-parsers/
     *
     * @param int $component `PHP_URL_SCHEME`, `PHP_URL_HOST`, `PHP_URL_PORT`,
     * `PHP_URL_USER`, `PHP_URL_PASS`, `PHP_URL_PATH`, `PHP_URL_QUERY` or
     * `PHP_URL_FRAGMENT` for the given component, or `-1` for all components.
     * @return (
     *     $component is -1
     *     ? array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string}|false
     *     : ($component is \PHP_URL_PORT
     *         ? int|null|false
     *         : string|null|false
     *     )
     * )
     */
    public static function parse(string $uri, int $component = -1);

    /**
     * Get the components of the URI
     *
     * @return array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string}
     */
    public function getComponents(): array;

    /**
     * Check if the URI is a relative reference
     */
    public function isRelativeReference(): bool;

    /**
     * Remove "/./" and "/../" segments from the path of the URI before
     * optionally applying scheme- and protocol-based normalisation
     *
     * It isn't necessary to call this method for scheme, host and
     * percent-encoded octet normalisation, which are always applied.
     *
     * @return static
     */
    public function normalise(): UriInterface;

    /**
     * Transform a URI reference to a target URI
     *
     * The base URI is the one on which this method is called.
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
