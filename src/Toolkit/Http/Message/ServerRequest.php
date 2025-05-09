<?php declare(strict_types=1);

namespace Salient\Http\Message;

use Psr\Http\Message\MessageInterface as PsrMessageInterface;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Salient\Contract\Http\Message\ServerRequestInterface;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Salient\Utility\Arr;

/**
 * @api
 *
 * @extends AbstractRequest<PsrServerRequestInterface>
 */
class ServerRequest extends AbstractRequest implements ServerRequestInterface
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
    /** @var mixed[] */
    protected array $Attributes = [];

    /**
     * @api
     *
     * @param mixed[] $serverParams
     */
    final public function __construct(
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
    public static function fromPsr7(PsrMessageInterface $message): ServerRequest
    {
        return $message instanceof static
            ? $message
            : (new static(
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
                ->with('Attributes', $message->getAttributes());
    }

    /**
     * @inheritDoc
     *
     * @return mixed[]
     */
    public function getServerParams(): array
    {
        return $this->ServerParams;
    }

    /**
     * @inheritDoc
     *
     * @return mixed[]
     */
    public function getCookieParams(): array
    {
        return $this->CookieParams;
    }

    /**
     * @inheritDoc
     *
     * @return mixed[]
     */
    public function getQueryParams(): array
    {
        return $this->QueryParams;
    }

    /**
     * @inheritDoc
     *
     * @return mixed[]
     */
    public function getUploadedFiles(): array
    {
        return $this->UploadedFiles;
    }

    /**
     * @inheritDoc
     *
     * @return mixed[]|object|null
     */
    public function getParsedBody()
    {
        return $this->ParsedBody;
    }

    /**
     * @inheritDoc
     *
     * @return mixed[]
     */
    public function getAttributes(): array
    {
        return $this->Attributes;
    }

    /**
     * @inheritDoc
     *
     * @return mixed
     */
    public function getAttribute(string $name, $default = null)
    {
        return array_key_exists($name, $this->Attributes)
            ? $this->Attributes[$name]
            : $default;
    }

    /**
     * @inheritDoc
     *
     * @param mixed[] $cookies
     */
    public function withCookieParams(array $cookies): PsrServerRequestInterface
    {
        return $this->with('CookieParams', $cookies);
    }

    /**
     * @inheritDoc
     *
     * @param mixed[] $query
     */
    public function withQueryParams(array $query): PsrServerRequestInterface
    {
        return $this->with('QueryParams', $query);
    }

    /**
     * @inheritDoc
     *
     * @param mixed[] $uploadedFiles
     */
    public function withUploadedFiles(array $uploadedFiles): PsrServerRequestInterface
    {
        return $this->with('UploadedFiles', $uploadedFiles);
    }

    /**
     * @inheritDoc
     *
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
        return $this->with('Attributes', Arr::set($this->Attributes, $name, $value));
    }

    /**
     * @inheritDoc
     */
    public function withoutAttribute(string $name): PsrServerRequestInterface
    {
        return $this->with('Attributes', Arr::unset($this->Attributes, $name));
    }

    /**
     * @template T
     *
     * @param T $data
     * @return T
     */
    private function filterParsedBody($data)
    {
        if ($data !== null && !is_array($data) && !is_object($data)) {
            throw new InvalidArgumentTypeException(
                1,
                'data',
                'mixed[]|object|null',
                $data,
            );
        }
        return $data;
    }
}
