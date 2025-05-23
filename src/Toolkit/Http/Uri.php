<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Http\UriInterface;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use InvalidArgumentException;
use Stringable;

/**
 * @api
 */
class Uri implements UriInterface, HasHttpRegex
{
    use ImmutableTrait;

    /**
     * Replace empty HTTP and HTTPS paths with "/"
     */
    public const NORMALISE_EMPTY_PATH = 1;

    /**
     * Replace two or more subsequent slashes in a path (e.g. "//") with a
     * single slash ("/")
     */
    public const NORMALISE_MULTIPLE_SLASHES = 2;

    /**
     * @var array<string,int>
     */
    protected const SCHEME_PORT = [
        'http' => 80,
        'https' => 443,
    ];

    private const COMPONENT_NAME = [
        \PHP_URL_SCHEME => 'scheme',
        \PHP_URL_HOST => 'host',
        \PHP_URL_PORT => 'port',
        \PHP_URL_USER => 'user',
        \PHP_URL_PASS => 'pass',
        \PHP_URL_PATH => 'path',
        \PHP_URL_QUERY => 'query',
        \PHP_URL_FRAGMENT => 'fragment',
    ];

    private ?string $Scheme = null;
    private ?string $User = null;
    private ?string $Password = null;
    private ?string $Host = null;
    private ?int $Port = null;
    private string $Path = '';
    private ?string $Query = null;
    private ?string $Fragment = null;

    /**
     * @param bool $strict If `false`, unencoded characters are percent-encoded
     * before parsing.
     */
    final public function __construct(string $uri = '', bool $strict = false)
    {
        if ($uri === '') {
            return;
        }

        if (!$strict) {
            $uri = $this->encode($uri);
        }

        if (!Regex::match(self::URI_REGEX, $uri, $parts, \PREG_UNMATCHED_AS_NULL)) {
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
     * @param PsrUriInterface|Stringable|string $uri
     * @return static
     */
    public static function from($uri): self
    {
        return $uri instanceof static
            ? $uri
            : new static((string) $uri);
    }

    /**
     * @inheritDoc
     *
     * @param bool $strict If `false`, unencoded characters are percent-encoded
     * before parsing.
     */
    public static function parse(string $uri, int $component = -1, bool $strict = false)
    {
        try {
            $parts = (new static($uri, $strict))->getComponents();
        } catch (InvalidArgumentException $ex) {
            return false;
        }

        if ($component === -1) {
            return $parts;
        }

        $name = self::COMPONENT_NAME[$component] ?? null;
        if ($name === null) {
            throw new InvalidArgumentException(
                sprintf('Invalid component: %d', $component),
            );
        }
        return $parts[$name] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getComponents(): array
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
     * @inheritDoc
     */
    public function isRelativeReference(): bool
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
            $this->Scheme !== null
            && isset(static::SCHEME_PORT[$this->Scheme])
            && static::SCHEME_PORT[$this->Scheme] === $this->Port
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
    public function withScheme(string $scheme): PsrUriInterface
    {
        return $this
            ->with('Scheme', $this->filterScheme($scheme))
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withUserInfo(string $user, ?string $password = null): PsrUriInterface
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
    public function withHost(string $host): PsrUriInterface
    {
        return $this
            ->with('Host', $this->filterHost(Str::coalesce($host, null)))
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withPort(?int $port): PsrUriInterface
    {
        return $this
            ->with('Port', $this->filterPort($port))
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withPath(string $path): PsrUriInterface
    {
        return $this
            ->with('Path', $this->filterPath($path))
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withQuery(string $query): PsrUriInterface
    {
        $query = Str::coalesce($query, null);
        return $this
            ->with('Query', $this->filterQueryOrFragment($query));
    }

    /**
     * @inheritDoc
     */
    public function withFragment(string $fragment): PsrUriInterface
    {
        $fragment = Str::coalesce($fragment, null);
        return $this
            ->with('Fragment', $this->filterQueryOrFragment($fragment));
    }

    /**
     * @inheritDoc
     *
     * @param int-mask-of<Uri::NORMALISE_*> $flags
     */
    public function normalise(int $flags = Uri::NORMALISE_EMPTY_PATH): UriInterface
    {
        $uri = $this->removeDotSegments();

        if (
            $flags & self::NORMALISE_EMPTY_PATH
            && $uri->Path === ''
            && ($uri->Scheme === 'http' || $uri->Scheme === 'https')
        ) {
            $uri = $uri->withPath('/');
        }

        if (
            $flags & self::NORMALISE_MULTIPLE_SLASHES
            && strpos($uri->Path, '//') !== false
        ) {
            $uri = $uri->withPath(Regex::replace('/\/\/++/', '/', $uri->Path));
        }

        return $uri;
    }

    /**
     * @inheritDoc
     */
    public function follow($reference): UriInterface
    {
        if ($this->isRelativeReference()) {
            throw new InvalidArgumentException(
                'Reference cannot be resolved relative to another reference'
            );
        }

        // Compliant with [RFC3986] Section 5.2.2 ("Transform References")
        $reference = static::from($reference);
        if (!$reference->isRelativeReference()) {
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
                return $target->withQuery($reference->Query);
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
            $authority !== ''
            || $this->Host !== null
            || $this->Scheme === 'file'
        ) {
            $uri .= "//$authority";
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
     * @return static
     */
    private function mergeRelativePath(string $path): self
    {
        // As per [RFC3986] Section 5.2.3 ("Merge Paths")
        if ($this->getAuthority() !== '' && $this->Path === '') {
            return $this->withPath("/$path");
        }

        if (strpos($this->Path, '/') === false) {
            return $this->withPath($path);
        }

        $merge = implode('/', Arr::pop(explode('/', $this->Path)));
        return $this->withPath("$merge/$path");
    }

    /**
     * @return static
     */
    private function removeDotSegments(): self
    {
        // As per [RFC3986] Section 5.2.4 ("Remove Dot Segments")
        return $this->isRelativeReference()
            ? $this
            : $this->withPath(File::resolvePath($this->Path, true));
    }

    private function filterScheme(?string $scheme, bool $validate = true): ?string
    {
        if ($scheme === null || $scheme === '') {
            return null;
        }

        if ($validate && !Regex::match(self::SCHEME_REGEX, $scheme)) {
            throw new InvalidArgumentException(
                sprintf('Invalid scheme: %s', $scheme)
            );
        }

        return Str::lower($scheme);
    }

    private function filterUserInfoPart(?string $part): ?string
    {
        if ($part === null || $part === '') {
            return $part;
        }

        return $this->normaliseComponent($part, '[]#/:?@[]');
    }

    private function filterHost(?string $host, bool $validate = true): ?string
    {
        if ($host === null || $host === '') {
            return $host;
        }

        if ($validate) {
            $host = $this->encode($host);
            if (!Regex::match(self::HOST_REGEX, $host)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid host: %s', $host)
                );
            }
        }

        $host = Str::lower($this->decodeUnreserved($host));
        return $this->normaliseComponent($host, '[#/?@]', false);
    }

    /**
     * @param string|int|null $port
     */
    private function filterPort($port): ?int
    {
        if ($port === null || $port === '') {
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
        if ($path === null || $path === '') {
            return '';
        }

        return $this->normaliseComponent($path, '[]#?[]');
    }

    private function filterQueryOrFragment(?string $part): ?string
    {
        if ($part === null || $part === '') {
            return $part;
        }

        return $this->normaliseComponent($part, '[]#[]');
    }

    /**
     * @param bool $decodeUnreserved `false` if unreserved characters are
     * already decoded.
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

        return Regex::replaceCallbackArray([
            // Use uppercase hexadecimal digits
            '/%([0-9a-f]{2})/i' =>
                fn($matches) => '%' . Str::upper($matches[1]),
            // Encode everything except reserved and unreserved characters
            "/(?:%(?![0-9a-f]{2})|{$encodeRegex}[^]!#\$%&'()*+,\/:;=?@[])+/i" =>
                fn($matches) => rawurlencode($matches[0]),
        ], $part);
    }

    private function decodeUnreserved(string $part): string
    {
        return Regex::replaceCallback(
            '/%(2[de]|5f|7e|3[0-9]|[46][1-9a-f]|[57][0-9a])/i',
            fn($matches) => chr((int) hexdec($matches[1])),
            $part,
        );
    }

    /**
     * Percent-encode every character in a URI or URI component except reserved,
     * unreserved and pre-encoded characters
     */
    private function encode(string $partOrUri): string
    {
        return Regex::replaceCallback(
            '/(?:%(?![0-9a-f]{2})|[^]!#$%&\'()*+,\/:;=?@[])+/i',
            fn($matches) => rawurlencode($matches[0]),
            $partOrUri,
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
                $this->Scheme === null
                && $this->Path !== ''
                && Regex::match('/^[^\/:]*+:/', $this->Path)
            ) {
                throw new InvalidArgumentException(
                    'Path cannot begin with colon segment in URI without scheme'
                );
            }
        } elseif ($this->Path !== '' && $this->Path[0] !== '/') {
            throw new InvalidArgumentException(
                'Path must be empty or begin with "/" in URI with authority'
            );
        }

        return $this;
    }
}
