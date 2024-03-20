<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Core\Immutable;
use JsonSerializable;
use Stringable;

interface UriInterface extends
    PsrUriInterface,
    Stringable,
    JsonSerializable,
    Immutable
{
    /**
     * Parse a URI into its components
     *
     * Using multiple URI parsers can introduce inconsistent behaviour and
     * security vulnerabilities, so it may be important to use this method
     * instead of {@see parse_url()}.
     *
     * @link https://claroty.com/team82/research/white-papers/exploiting-url-parsing-confusion
     * @link https://daniel.haxx.se/blog/2022/01/10/dont-mix-url-parsers/
     *
     * @return array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string}
     */
    public static function parse(string $uri): array;

    /**
     * Get the URI as an array of components
     *
     * Components not present in the URI are not returned.
     *
     * @return array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string}
     */
    public function toParts(): array;

    /**
     * Check if the URI is not absolute
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
}
