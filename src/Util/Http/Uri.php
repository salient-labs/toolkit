<?php declare(strict_types=1);

namespace Lkrms\Http;

use Lkrms\Concern\Immutable;
use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Utility\Arr;
use Lkrms\Utility\File;
use Lkrms\Utility\Pcre;
use Lkrms\Utility\Str;
use Psr\Http\Message\UriInterface;
use JsonSerializable;
use Stringable;

/**
 * An [RFC3986]-compliant URI
 */
class Uri implements JsonSerializable, Stringable, UriInterface
{
    use Immutable {
        withPropertyValue as with;
    }

    protected const SCHEME_PORT = [
        'http' => 80,
        'https' => 443,
    ];

    private const URI_SCHEME = '/^[a-z][-a-z0-9+.]*$/i';

    private const URI_HOST = '/^(([-a-z0-9!$&\'()*+,.;=_~]|%[0-9a-f]{2})++|\[[0-9a-f:]++\])$/i';

    private const URI = <<<'REGEX'
        ` ^
        (?(DEFINE)
          (?<unreserved> [-a-z0-9._~] )
          (?<sub_delims> [!$&'()*+,;=] )
          (?<pct_encoded> % [0-9a-f]{2} )
          (?<reg_char> (?&unreserved) | (?&pct_encoded) | (?&sub_delims) )
          (?<pchar> (?&reg_char) | [:@] )
        )
        (?: (?<scheme> [a-z] [-a-z0-9+.]* ) : )?+
        (?:
          //
          (?<authority>
            (?:
              (?<userinfo>
                (?<user> (?&reg_char)* )
                (?: : (?<pass> (?: (?&reg_char) | : )* ) )?
              )
              @
            )?+
            (?<host> (?&reg_char)*+ | \[ (?<ipv6address> [0-9a-f:]++ ) \] )
            (?: : (?<port> [0-9]+ ) )?+
          )
          # Path after authority must be empty or begin with "/"
          (?= / | \? | \# | $ ) |
          # Path cannot begin with "//" except after authority
          (?= / ) (?! // ) |
          # Rootless paths can only begin with a ":" segment after scheme
          (?(<scheme>) (?= (?&pchar) ) | (?= (?&reg_char) | @ ) (?! [^/:]++ : ) ) |
          (?= \? | \# | $ )
        )
        (?<path> (?: (?&pchar) | / )*+ )
        (?: \? (?<query>    (?: (?&pchar) | [?/] )* ) )?+
        (?: \# (?<fragment> (?: (?&pchar) | [?/] )* ) )?+
        $ `xi
        REGEX;

    protected ?string $Scheme = null;
    protected ?string $User = null;
    protected ?string $Password = null;
    protected ?string $Host = null;
    protected ?int $Port = null;
    protected string $Path = '';
    protected ?string $Query = null;
    protected ?string $Fragment = null;

    /**
     * Creates a new Uri object
     *
     * @param bool $strict If `false`, unencoded characters are percent-encoded
     * before parsing.
     */
    final public function __construct(string $uri = '', bool $strict = true)
    {
        if ($uri === '') {
            return;
        }

        if (!$strict) {
            $uri = $this->encode($uri);
        }

        if (!Pcre::match(
            self::URI,
            $uri,
            $parts,
            \PREG_UNMATCHED_AS_NULL
        )) {
            throw new InvalidArgumentException(sprintf('Invalid URI: %s', $uri));
        }

        $this->Scheme = $this->filterScheme($parts['scheme'], false);
        $this->User = $this->filterUserInfoPart($parts['user']);
        $this->Password = $this->filterUserInfoPart($parts['pass']);
        $this->Host = $this->filterHost($parts['host'], false);
        $this->Port = $this->filterPort($parts['port']);
        $this->Path = $this->filterPath($parts['path']);
        $this->Query = $this->filterQueryOrFragment($parts['query']);
        $this->Fragment = $this->filterQueryOrFragment($parts['fragment']);

        if ($this->Password !== null) {
            $this->User ??= '';
        }
    }

    /**
     * Creates a new Uri object from an array of URI components
     *
     * Accepts arrays returned by {@see parse_url()}, {@see Uri::parse()} and
     * {@see Uri::toParts()}.
     *
     * @param array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string} $parts
     * @return static
     */
    public static function fromParts(array $parts): self
    {
        $uri = new static();
        $uri->applyParts($parts);
        return $uri;
    }

    /**
     * Parse a URI into its components
     *
     * Replacement for {@see parse_url()}.
     *
     * @param bool $strict If `false`, unencoded characters are percent-encoded
     * before parsing.
     * @return array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string}
     */
    public static function parse(string $uri, bool $strict = true): array
    {
        return (new static($uri, $strict))->toParts();
    }

    /**
     * Convert an array of URI components to a URI
     *
     * Accepts arrays returned by {@see parse_url()}, {@see Uri::parse()} and
     * {@see Uri::toParts()}.
     *
     * @param array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string} $parts
     */
    public static function unparse(array $parts): string
    {
        return (string) static::fromParts($parts);
    }

    /**
     * Resolve a URI reference that may be relative to a given base URI to a
     * target URI
     *
     * @param UriInterface|Stringable|string $reference
     * @param UriInterface|Stringable|string $baseUri
     */
    public static function resolveReference($reference, $baseUri): string
    {
        return (string) static::from($baseUri)->follow(static::from($reference));
    }

    /**
     * Get the URI as an array of components
     *
     * Components not present in the URI are not returned.
     *
     * @return array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string}
     */
    public function toParts(): array
    {
        return Arr::whereNotNull([
            'scheme' => $this->Scheme,
            'host' => $this->Host,
            'port' => $this->getPort(),
            'user' => $this->User,
            'pass' => $this->Password,
            'path' => Str::coalesce($this->Path, null),
            'query' => $this->Query,
            'fragment' => $this->Fragment,
        ]);
    }

    /**
     * True if the URI is not absolute
     */
    public function isReference(): bool
    {
        return $this->Scheme === null;
    }

    /**
     * @inheritDoc
     */
    public function getScheme(): string
    {
        return (string) $this->Scheme;
    }

    /**
     * @inheritDoc
     */
    public function getAuthority(): string
    {
        $authority = '';

        if ($this->User !== null) {
            $authority .= $this->getUserInfo() . '@';
        }

        $authority .= $this->Host;

        $port = $this->getPort();
        if ($port !== null) {
            $authority .= ':' . $port;
        }

        return $authority;
    }

    /**
     * @inheritDoc
     */
    public function getUserInfo(): string
    {
        if ($this->Password !== null) {
            return $this->User . ':' . $this->Password;
        }

        return (string) $this->User;
    }

    /**
     * @inheritDoc
     */
    public function getHost(): string
    {
        return (string) $this->Host;
    }

    /**
     * @inheritDoc
     */
    public function getPort(): ?int
    {
        if (
            $this->Scheme !== null &&
            isset(static::SCHEME_PORT[$this->Scheme]) &&
            static::SCHEME_PORT[$this->Scheme] === $this->Port
        ) {
            return null;
        }

        return $this->Port;
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return $this->Path;
    }

    /**
     * @inheritDoc
     */
    public function getQuery(): string
    {
        return (string) $this->Query;
    }

    /**
     * @inheritDoc
     */
    public function getFragment(): string
    {
        return (string) $this->Fragment;
    }

    /**
     * @inheritDoc
     */
    public function withScheme(string $scheme): UriInterface
    {
        return $this
            ->with('Scheme', $this->filterScheme($scheme))
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        if ($user === '') {
            $user = null;
            $password = null;
        } else {
            $user = $this->filterUserInfoPart($user);
            $password = $this->filterUserInfoPart($password);
        }

        return $this
            ->with('User', $user)
            ->with('Password', $password)
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withHost(string $host): UriInterface
    {
        return $this
            ->with('Host', $this->filterHost(Str::coalesce($host, null)))
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withPort(?int $port): UriInterface
    {
        return $this
            ->with('Port', $this->filterPort($port))
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withPath(string $path): UriInterface
    {
        return $this
            ->with('Path', $this->filterPath($path))
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withQuery(string $query): UriInterface
    {
        return $this
            ->with('Query', $this->filterQueryOrFragment(Str::coalesce($query, null)));
    }

    /**
     * @inheritDoc
     */
    public function withFragment(string $fragment): UriInterface
    {
        return $this
            ->with('Fragment', $this->filterQueryOrFragment(Str::coalesce($fragment, null)));
    }

    /**
     * Get a normalised instance
     *
     * Removes "/./" and "/../" segments from the path. \[RFC3986]-compliant
     * scheme- and protocol-based normalisation may also be performed.
     *
     * Scheme, host and percent-encoded octets in the URI are always normalised.
     *
     * @see Uri::removeDotSegments()
     *
     * @return static
     */
    public function normalise(): self
    {
        return $this->removeDotSegments();
    }

    /**
     * Resolve a URI reference that may be relative to the URI to a target URI
     *
     * Implements \[RFC3986] Section 5.2.2 ("Transform References").
     *
     * @param UriInterface|Stringable|string $reference
     * @return static
     */
    public function follow($reference): self
    {
        if ($this->isReference()) {
            throw new InvalidArgumentException(
                'Reference cannot be resolved relative to another reference'
            );
        }

        $reference = static::from($reference);
        if (!$reference->isReference()) {
            return $reference->removeDotSegments();
        }

        $target = $this->withFragment((string) $reference->Fragment);

        if ($reference->getAuthority() !== '') {
            return $target
                ->withHost((string) $reference->Host)
                ->withPort($reference->Port)
                ->withUserInfo((string) $reference->User, $reference->Password)
                ->withPath($reference->removeDotSegments()->Path)
                ->withQuery((string) $reference->Query);
        }

        if ($reference->Path === '') {
            if ($reference->Query !== null) {
                return $target
                    ->withQuery($reference->Query);
            }
            return $target;
        }

        $target = $target->withQuery((string) $reference->Query);

        if ($reference->Path[0] === '/') {
            return $target
                ->withPath($reference->Path)
                ->removeDotSegments();
        }

        return $target
            ->mergeRelativePath($reference->Path)
            ->removeDotSegments();
    }

    /**
     * Get an instance with "/./" and "/../" segments removed from the path
     *
     * Compliant with \[RFC3986] Section 5.2.4 ("Remove Dot Segments").
     *
     * @return static
     */
    public function removeDotSegments(): self
    {
        // Relative references can only be resolved relative to an absolute URI
        if ($this->isReference()) {
            return $this;
        }

        return $this->withPath(File::resolve($this->Path, true));
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $uri = '';

        if ($this->Scheme !== null) {
            $uri .= "{$this->Scheme}:";
        }

        $authority = $this->getAuthority();
        if (
            $authority !== '' ||
            $this->Host !== null ||
            $this->Scheme === 'file'
        ) {
            $uri .= "//{$authority}";
        }

        $uri .= $this->Path;

        if ($this->Query !== null) {
            $uri .= "?{$this->Query}";
        }

        if ($this->Fragment !== null) {
            $uri .= "#{$this->Fragment}";
        }

        return $uri;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): string
    {
        return $this->__toString();
    }

    /**
     * Resolve a value to a Uri object
     *
     * @param UriInterface|Stringable|string $uri
     * @return static
     */
    public static function from($uri): self
    {
        if ($uri instanceof static) {
            return $uri;
        }
        return new static((string) $uri);
    }

    /**
     * Get an instance with a relative path merged into the path of the URI
     *
     * Implements \[RFC3986] Section 5.2.3 ("Merge Paths").
     *
     * @return static
     */
    private function mergeRelativePath(string $path): self
    {
        if ($this->getAuthority() !== '' && $this->Path === '') {
            return $this->withPath("/$path");
        }

        if (strpos($this->Path, '/') === false) {
            return $this->withPath($path);
        }

        $merge = implode('/', Arr::pop(explode('/', $this->Path)));
        return $this->withPath("{$merge}/{$path}");
    }

    /**
     * @param array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string} $parts
     */
    private function applyParts(array $parts): void
    {
        $this->Scheme = $this->filterScheme($parts['scheme'] ?? null);
        $this->User = $this->filterUserInfoPart($parts['user'] ?? null);
        $this->Password = $this->filterUserInfoPart($parts['pass'] ?? null);
        $this->Host = $this->filterHost($parts['host'] ?? null);
        $this->Port = $this->filterPort($parts['port'] ?? null);
        $this->Path = $this->filterPath($parts['path'] ?? null);
        $this->Query = $this->filterQueryOrFragment($parts['query'] ?? null);
        $this->Fragment = $this->filterQueryOrFragment($parts['fragment'] ?? null);

        if ($this->Password !== null) {
            $this->User ??= '';
        }
    }

    private function filterScheme(?string $scheme, bool $validate = true): ?string
    {
        if ((string) $scheme === '') {
            return null;
        }
        if ($validate && !Pcre::match(self::URI_SCHEME, $scheme)) {
            throw new InvalidArgumentException(
                sprintf('Invalid scheme: %s', $scheme)
            );
        }
        return Str::lower($scheme);
    }

    private function filterUserInfoPart(?string $part): ?string
    {
        if ((string) $part === '') {
            return $part;
        }
        return $this->normaliseComponent($part, '[]#/:?@[]');
    }

    private function filterHost(?string $host, bool $validate = true): ?string
    {
        if ((string) $host === '') {
            return $host;
        }
        if ($validate) {
            $host = $this->encode($host);
            if (!Pcre::match(self::URI_HOST, $host)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid host: %s', $host)
                );
            }
        }
        return $this->normaliseComponent(
            Str::lower($this->decodeUnreserved($host)),
            '[#/?@]',
            false
        );
    }

    /**
     * @param string|int|null $port
     */
    private function filterPort($port): ?int
    {
        if ((string) $port === '') {
            return null;
        }
        $port = (int) $port;
        if ($port < 0 || $port > 65535) {
            throw new InvalidArgumentException(
                sprintf('Invalid port: %d', $port)
            );
        }
        return $port;
    }

    private function filterPath(?string $path): string
    {
        if ((string) $path === '') {
            return '';
        }
        return $this->normaliseComponent($path, '[]#?[]');
    }

    private function filterQueryOrFragment(?string $part): ?string
    {
        if ((string) $part === '') {
            return $part;
        }
        return $this->normaliseComponent($part, '[]#[]');
    }

    /**
     * Normalise a URI component
     *
     * @param bool $decodeUnreserved `false` if unreserved characters have
     * already been decoded.
     */
    private function normaliseComponent(
        string $part,
        string $encodeRegex = '',
        bool $decodeUnreserved = true
    ): string {
        if ($decodeUnreserved) {
            $part = $this->decodeUnreserved($part);
        }

        if ($encodeRegex !== '') {
            $encodeRegex = str_replace('/', '\/', $encodeRegex) . '|';
        }

        return Pcre::replaceCallbackArray([
            // Use uppercase hexadecimal digits
            '/%([0-9a-f]{2})/i' =>
                fn(array $matches) => '%' . Str::upper($matches[1]),
            // Encode everything except reserved and unreserved characters
            "/(?:%(?![0-9a-f]{2})|{$encodeRegex}[^]!#\$%&'()*+,\/:;=?@[])+/i" =>
                fn(array $matches) => rawurlencode($matches[0]),
        ], $part);
    }

    /**
     * Decode unreserved characters in a URI component
     */
    private function decodeUnreserved(string $part): string
    {
        return Pcre::replaceCallback(
            '/%(2[de]|5f|7e|3[0-9]|[46][1-9a-f]|[57][0-9a])/i',
            fn(array $matches) => chr(hexdec($matches[1])),
            $part
        );
    }

    /**
     * Percent-encode every character in a URI or URI component except reserved,
     * unreserved and pre-encoded characters
     */
    private function encode(string $partOrUri): string
    {
        return Pcre::replaceCallback(
            '/(?:%(?![0-9a-f]{2})|[^]!#$%&\'()*+,\/:;=?@[])+/i',
            fn(array $matches) => rawurlencode($matches[0]),
            $partOrUri
        );
    }

    /**
     * @return $this
     */
    private function validate(): self
    {
        if ($this->getAuthority() === '') {
            if (substr($this->Path, 0, 2) === '//') {
                throw new InvalidArgumentException(
                    'Path cannot begin with "//" in URI without authority'
                );
            }

            if (
                $this->Scheme === null &&
                $this->Path !== '' &&
                Pcre::match('/^[^\/:]*+:/', $this->Path)
            ) {
                throw new InvalidArgumentException(
                    'Path cannot begin with colon segment in URI without scheme'
                );
            }

            return $this;
        }

        if ($this->Path !== '' && $this->Path[0] !== '/') {
            throw new InvalidArgumentException(
                'Path must be empty or begin with "/" in URI with authority'
            );
        }

        return $this;
    }
}
