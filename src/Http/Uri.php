<?php declare(strict_types=1);

namespace Lkrms\Http;

use Lkrms\Concern\Immutable;
use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Pcre;
use Lkrms\Utility\Str;
use Psr\Http\Message\UriInterface;

/**
 * Represents a valid [RFC3986]-compliant URI
 */
class Uri implements UriInterface
{
    use Immutable;

    protected const SCHEME_PORT = [
        'http' => 80,
        'https' => 443,
    ];

    protected string $Scheme = '';
    protected string $User = '';
    protected string $Password = '';
    protected string $Host = '';
    protected ?int $Port = null;
    protected string $Path = '';
    protected string $Query = '';
    protected string $Fragment = '';

    /**
     * Creates a new Uri object
     *
     * @param bool $strict If `false`, percent-encode unencoded characters
     * before parsing.
     */
    public function __construct(string $uri = '', bool $strict = true)
    {
        if ($uri === '') {
            return;
        }

        if (!$strict) {
            $uri = $this->encode($uri);
        }

        if (!Pcre::match(
            Regex::anchorAndDelimit(Regex::URI),
            $uri,
            $parts,
            PREG_UNMATCHED_AS_NULL
        )) {
            throw new InvalidArgumentException(sprintf('Invalid URI: %s', $uri));
        }

        $this->Scheme = $this->normaliseScheme($parts['scheme']);
        $this->User = $this->normaliseUserInfoPart($parts['user']);
        $this->Password = $this->normaliseUserInfoPart($parts['pass']);
        $this->Host = $this->normaliseHost($parts['host']);
        $this->Port = $this->normalisePort($parts['port']);
        $this->Path = $this->normalisePath($parts['path']);
        $this->Query = $this->normaliseQueryOrFragment($parts['query']);
        $this->Fragment = $this->normaliseQueryOrFragment($parts['fragment']);
    }

    /**
     * @inheritDoc
     */
    public function getScheme(): string
    {
        return $this->Scheme;
    }

    /**
     * @inheritDoc
     */
    public function getAuthority(): string
    {
        return Arr::implode(':', [
            Arr::implode('@', [
                $this->getUserInfo(),
                $this->Host,
            ]),
            (string) $this->getPort(),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getUserInfo(): string
    {
        return Arr::implode(':', [
            $this->User,
            $this->Password,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getHost(): string
    {
        return $this->Host;
    }

    /**
     * @inheritDoc
     */
    public function getPort(): ?int
    {
        if (
            $this->Scheme !== '' &&
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
        return $this->Query;
    }

    /**
     * @inheritDoc
     */
    public function getFragment(): string
    {
        return $this->Fragment;
    }

    /**
     * @inheritDoc
     */
    public function withScheme(string $scheme): self
    {
        if (
            $scheme !== '' &&
            !Pcre::match(Regex::anchorAndDelimit(Regex::URI_SCHEME), $scheme)
        ) {
            throw new InvalidArgumentException(sprintf('Invalid scheme: %s', $scheme));
        }
        return $this
            ->withPropertyValue('Scheme', $this->normaliseScheme($scheme))
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withUserInfo(string $user, ?string $password = null): self
    {
        return $this
            ->withPropertyValue('User', $this->normaliseUserInfoPart($user))
            ->withPropertyValue('Password', $this->normaliseUserInfoPart($password))
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withHost(string $host): self
    {
        $host = $this->encode($host);
        if (
            $host !== '' &&
            !Pcre::match(Regex::anchorAndDelimit(Regex::URI_HOST), $host)
        ) {
            throw new InvalidArgumentException(sprintf('Invalid host: %s', $host));
        }
        return $this
            ->withPropertyValue('Host', $this->normaliseHost($host))
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withPort(?int $port): self
    {
        return $this
            ->withPropertyValue('Port', $this->normalisePort($port))
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withPath(string $path): self
    {
        return $this
            ->withPropertyValue('Path', $this->normalisePath($path))
            ->validate();
    }

    /**
     * @inheritDoc
     */
    public function withQuery(string $query): self
    {
        return $this->withPropertyValue('Query', $this->normaliseQueryOrFragment($query));
    }

    /**
     * @inheritDoc
     */
    public function withFragment(string $fragment): self
    {
        return $this->withPropertyValue('Fragment', $this->normaliseQueryOrFragment($fragment));
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        if ($this->Scheme !== '') {
            $parts[] = "{$this->Scheme}:";
        }

        $authority = $this->getAuthority();
        if ($authority !== '') {
            $parts[] = "//{$authority}";
        }

        $parts[] = $this->Path;

        if ($this->Query !== '') {
            $parts[] = "?{$this->Query}";
        }

        if ($this->Fragment !== '') {
            $parts[] = "#{$this->Fragment}";
        }

        return implode('', $parts);
    }

    private function normaliseScheme(?string $scheme): string
    {
        if ($scheme === null || $scheme === '') {
            return '';
        }
        return Str::lower($scheme);
    }

    private function normaliseUserInfoPart(?string $part): string
    {
        if ($part === null || $part === '') {
            return '';
        }
        return $this->normalise($part, '[]#/:?@[]');
    }

    private function normaliseHost(?string $host): string
    {
        if ($host === null || $host === '') {
            return '';
        }
        return $this->normalise(Str::lower($this->decodeUnreserved($host)), '[#/?@]', false);
    }

    /**
     * @param string|int|null $port
     */
    private function normalisePort($port): ?int
    {
        if ($port === null || $port === '') {
            return null;
        }
        $port = (int) $port;
        if ($port < 0 || $port > 65535) {
            throw new InvalidArgumentException(sprintf('Invalid port: %d', $port));
        }
        return $port;
    }

    private function normalisePath(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }
        return $this->normalise($path, '[]#?[]');
    }

    private function normaliseQueryOrFragment(?string $part): string
    {
        if ($part === null || $part === '') {
            return '';
        }
        return $this->normalise($part, '[]#[]');
    }

    /**
     * Normalise a URI component
     *
     * @param bool $decodeUnreserved `false` if unreserved characters have
     * already been decoded.
     */
    private function normalise(
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

        return
            Pcre::replaceCallbackArray([
                // Use uppercase hexadecimal digits
                '/%([0-9a-f]{2})/i' =>
                    fn(array $matches) =>
                        '%' . strtoupper($matches[1]),
                // Encode everything except reserved and unreserved characters
                "/(%(?![0-9a-f]{2})|{$encodeRegex}[^]!#\$%&'()*+,\/:;=?@[])+/i" =>
                    fn(array $matches) =>
                        rawurlencode($matches[0]),
            ], $part);
    }

    /**
     * Decode unreserved characters in a URI component
     */
    private function decodeUnreserved(string $part): string
    {
        return
            Pcre::replaceCallback(
                '/%(2[de]|5f|7e|3[0-9]|[46][1-9a-f]|[57][0-9a])/i',
                fn(array $matches) =>
                    chr(hexdec($matches[1])),
                $part
            );
    }

    /**
     * Percent-encode every character in a URI or URI component except reserved,
     * unreserved and pre-encoded characters
     */
    private function encode(string $partOrUri): string
    {
        return
            Pcre::replaceCallback(
                '/(%(?![0-9a-f]{2})|[^]!#$%&\'()*+,\/:;=?@[])+/i',
                fn(array $matches) =>
                    rawurlencode($matches[0]),
                $partOrUri
            );
    }

    private function validate(): self
    {
        if (
            $this->Host === '' &&
            ($this->getUserInfo() !== '' || $this->Port !== null)
        ) {
            throw new InvalidArgumentException('URI without host cannot have userinfo or port');
        }

        if ($this->getAuthority() === '') {
            if (substr($this->Path, 0, 2) === '//') {
                throw new InvalidArgumentException('Path cannot begin with "//" in URI without authority');
            }
            if (
                $this->Scheme === '' &&
                $this->Path !== '' &&
                Pcre::match('/^[^\/:]*+:/', $this->Path)
            ) {
                throw new InvalidArgumentException('Path cannot begin with colon segment in URI without scheme');
            }
            return $this;
        }

        if ($this->Path !== '' && $this->Path[0] !== '/') {
            throw new InvalidArgumentException('Path must be empty or begin with "/" in URI with authority');
        }

        return $this;
    }
}
