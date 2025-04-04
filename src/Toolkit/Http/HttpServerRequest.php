<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\MessageInterface as PsrMessageInterface;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Http\Message\ServerRequestInterface;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Stringable;

/**
 * A PSR-7 request (incoming, server-side)
 */
class HttpServerRequest extends HttpRequest implements ServerRequestInterface
{
    use ImmutableTrait;

    /** @var mixed[] */
    protected array $ServerParams;
    /** @var mixed[] */
    protected array $CookieParams = [];
    /** @var mixed[] */
    protected array $QueryParams = [];
    /** @var mixed[] */
    protected array $UploadedFiles = [];
    /** @var mixed[]|object|null */
    protected $ParsedBody;
    /** @var array<string,mixed> */
    protected array $Attributes = [];

    /**
     * @param PsrUriInterface|Stringable|string $uri
     * @param mixed[] $serverParams
     * @param PsrStreamInterface|resource|string|null $body
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|null $headers
     */
    public function __construct(
        string $method,
        $uri,
        array $serverParams = [],
        $body = null,
        $headers = null,
        ?string $requestTarget = null,
        string $version = '1.1'
    ) {
        $this->ServerParams = $serverParams;

        parent::__construct($method, $uri, $body, $headers, $requestTarget, $version);
    }

    /**
     * @inheritDoc
     */
    public static function fromPsr7(PsrMessageInterface $message): HttpServerRequest
    {
        if ($message instanceof HttpServerRequest) {
            return $message;
        }

        if (!$message instanceof PsrServerRequestInterface) {
            throw new InvalidArgumentTypeException(1, 'message', PsrServerRequestInterface::class, $message);
        }

        /** @var array<string,mixed> */
        $attributes = $message->getAttributes();

        return (new self(
            $message->getMethod(),
            $message->getUri(),
            $message->getServerParams(),
            $message->getBody(),
            $message->getHeaders(),
            $message->getRequestTarget(),
            $message->getProtocolVersion(),
        ))
            ->withCookieParams($message->getCookieParams())
            ->withQueryParams($message->getQueryParams())
            ->withUploadedFiles($message->getUploadedFiles())
            ->withParsedBody($message->getParsedBody())
            ->with('Attributes', $attributes);
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
    public function withCookieParams(array $cookies): PsrServerRequestInterface
    {
        return $this->with('CookieParams', $cookies);
    }

    /**
     * @param mixed[] $query
     */
    public function withQueryParams(array $query): PsrServerRequestInterface
    {
        return $this->with('QueryParams', $query);
    }

    /**
     * @param mixed[] $uploadedFiles
     */
    public function withUploadedFiles(array $uploadedFiles): PsrServerRequestInterface
    {
        return $this->with('UploadedFiles', $uploadedFiles);
    }

    /**
     * @param mixed[]|object|null $data
     */
    public function withParsedBody($data): PsrServerRequestInterface
    {
        return $this->with('ParsedBody', $this->filterParsedBody($data));
    }

    /**
     * @inheritDoc
     */
    public function withAttribute(string $name, $value): PsrServerRequestInterface
    {
        $attributes = $this->Attributes;
        $attributes[$name] = $value;
        return $this->with('Attributes', $attributes);
    }

    /**
     * @inheritDoc
     */
    public function withoutAttribute(string $name): PsrServerRequestInterface
    {
        $attributes = $this->Attributes;
        unset($attributes[$name]);
        return $this->with('Attributes', $attributes);
    }

    /**
     * @template T
     *
     * @param T $data
     * @return T
     */
    private function filterParsedBody($data)
    {
        if ($data === null || is_array($data) || is_object($data)) {
            return $data;
        }
        throw new InvalidArgumentTypeException(1, 'data', 'mixed[]|object|null', $data);
    }
}
