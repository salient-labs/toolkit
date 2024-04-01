<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Http\HttpServerRequestInterface;
use Salient\Core\Exception\InvalidArgumentTypeException;
use Stringable;

/**
 * An incoming HTTP request
 */
class HttpServerRequest extends HttpRequest implements HttpServerRequestInterface
{
    /**
     * @var mixed[]
     */
    protected array $ServerParams;

    /**
     * @var mixed[]
     */
    protected array $CookieParams = [];

    /**
     * @var mixed[]
     */
    protected array $QueryParams = [];

    /**
     * @var mixed[]
     */
    protected array $UploadedFiles = [];

    /**
     * @var mixed[]|object|null
     */
    protected $ParsedBody;

    /**
     * @var array<string,mixed>
     */
    protected array $Attributes = [];

    /**
     * @param PsrUriInterface|Stringable|string $uri
     * @param mixed[] $serverParams
     * @param StreamInterface|resource|string|null $body
     * @param HttpHeadersInterface|array<string,string[]|string>|null $headers
     */
    public function __construct(
        string $method,
        $uri,
        array $serverParams = [],
        ?string $requestTarget = null,
        $body = null,
        $headers = null,
        string $version = '1.1'
    ) {
        $this->ServerParams = $serverParams;

        parent::__construct($method, $uri, $requestTarget, $body, $headers, $version);
    }

    /**
     * @return mixed[]
     */
    public function getServerParams(): array
    {
        return $this->ServerParams;
    }

    /**
     * @return mixed[]
     */
    public function getCookieParams(): array
    {
        return $this->CookieParams;
    }

    /**
     * @return mixed[]
     */
    public function getQueryParams(): array
    {
        return $this->QueryParams;
    }

    /**
     * @return mixed[]
     */
    public function getUploadedFiles(): array
    {
        return $this->UploadedFiles;
    }

    /**
     * @return mixed[]|object|null
     */
    public function getParsedBody()
    {
        return $this->ParsedBody;
    }

    /**
     * @return array<string,mixed>
     */
    public function getAttributes(): array
    {
        return $this->Attributes;
    }

    /**
     * @return mixed
     */
    public function getAttribute(string $name, $default = null)
    {
        if (!array_key_exists($name, $this->Attributes)) {
            return $default;
        }
        return $this->Attributes[$name];
    }

    /**
     * @param mixed[] $cookies
     */
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        return $this->with('CookieParams', $cookies);
    }

    /**
     * @param mixed[] $query
     */
    public function withQueryParams(array $query): ServerRequestInterface
    {
        return $this->with('QueryParams', $query);
    }

    /**
     * @param mixed[] $uploadedFiles
     */
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        return $this->with('UploadedFiles', $uploadedFiles);
    }

    /**
     * @param mixed[]|object|null $data
     */
    public function withParsedBody($data): ServerRequestInterface
    {
        return $this->with('ParsedBody', $this->filterParsedBody($data));
    }

    /**
     * @inheritDoc
     */
    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $attributes = $this->Attributes;
        $attributes[$name] = $value;
        return $this->with('Attributes', $attributes);
    }

    /**
     * @inheritDoc
     */
    public function withoutAttribute(string $name): ServerRequestInterface
    {
        $attributes = $this->Attributes;
        unset($attributes[$name]);
        return $this->with('Attributes', $attributes);
    }

    /**
     * @template T of mixed[]|object|null
     *
     * @param T $data
     * @return T
     */
    private function filterParsedBody($data)
    {
        if ($data === null || is_array($data) || is_object($data)) {
            return $data;
        }
        // @phpstan-ignore-next-line
        throw new InvalidArgumentTypeException(1, '$data', 'mixed[]|object|null', $data);
    }
}
