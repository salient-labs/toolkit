<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Salient\Contract\Http\HttpHeader;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Utility\Arr;

/**
 * An HTTP response
 */
class HttpResponse extends HttpMessage implements HttpResponseInterface
{
    protected const STATUS_CODE = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    protected int $StatusCode;
    protected ?string $ReasonPhrase;

    /**
     * Creates a new HttpResponse object
     *
     * @param StreamInterface|resource|string|null $body
     * @param HttpHeadersInterface|array<string,string[]|string>|null $headers
     */
    public function __construct(
        int $code = 200,
        ?string $reasonPhrase = null,
        $body = null,
        $headers = null,
        string $version = '1.1'
    ) {
        $this->StatusCode = $this->filterStatusCode($code);
        $this->ReasonPhrase = $this->filterReasonPhrase($code, $reasonPhrase);

        parent::__construct($body, $headers, $version);
    }

    /**
     * @inheritDoc
     */
    public function getStatusCode(): int
    {
        return $this->StatusCode;
    }

    /**
     * @inheritDoc
     */
    public function getReasonPhrase(): string
    {
        return (string) $this->ReasonPhrase;
    }

    /**
     * @inheritDoc
     */
    public function getHttpPayload(bool $withoutBody = false): string
    {
        return $this->withContentLength()->doGetHttpPayload($withoutBody);
    }

    /**
     * @inheritDoc
     */
    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        return $this
            ->with('StatusCode', $this->filterStatusCode($code))
            ->with('ReasonPhrase', $this->filterReasonPhrase($code, $reasonPhrase));
    }

    /**
     * Get an instance with the size of the message body applied to the content
     * length header
     *
     * @return static
     */
    public function withContentLength(): self
    {
        $size = $this->Body->getSize();
        if ($size !== null) {
            return $this->withHeader(HttpHeader::CONTENT_LENGTH, (string) $size);
        } else {
            return $this->withoutHeader(HttpHeader::CONTENT_LENGTH);
        }
    }

    /**
     * @inheritDoc
     */
    protected function getStartLine(): string
    {
        return Arr::implode(' ', [
            sprintf('HTTP/%s %d', $this->ProtocolVersion, $this->StatusCode),
            $this->ReasonPhrase,
        ]);
    }

    private function filterStatusCode(int $code): int
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException(
                sprintf('Invalid HTTP status code: %d', $code)
            );
        }
        return $code;
    }

    private function filterReasonPhrase(int $code, ?string $reasonPhrase): ?string
    {
        if ($reasonPhrase === null || $reasonPhrase === '') {
            return static::STATUS_CODE[$code] ?? null;
        }
        return $reasonPhrase;
    }

    private function doGetHttpPayload(bool $withoutBody): string
    {
        return parent::getHttpPayload($withoutBody);
    }
}
