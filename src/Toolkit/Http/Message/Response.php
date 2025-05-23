<?php declare(strict_types=1);

namespace Salient\Http\Message;

use Psr\Http\Message\MessageInterface as PsrMessageInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Salient\Contract\Http\Message\ResponseInterface;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Arr;
use Salient\Utility\Str;
use InvalidArgumentException;

/**
 * @api
 *
 * @extends AbstractMessage<PsrResponseInterface>
 */
class Response extends AbstractMessage implements ResponseInterface
{
    use ImmutableTrait;

    /**
     * @var array<int,string>
     */
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
     * @api
     */
    final public function __construct(
        int $code = 200,
        $body = null,
        $headers = null,
        ?string $reasonPhrase = null,
        string $version = '1.1'
    ) {
        $this->StatusCode = $this->filterStatusCode($code);
        $this->ReasonPhrase = $this->filterReasonPhrase($code, $reasonPhrase);

        parent::__construct($body, $headers, $version);
    }

    /**
     * @inheritDoc
     */
    public static function fromPsr7(PsrMessageInterface $message): Response
    {
        return $message instanceof static
            ? $message
            : new static(
                $message->getStatusCode(),
                $message->getBody(),
                $message->getHeaders(),
                $message->getReasonPhrase(),
                $message->getProtocolVersion(),
            );
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
    public function withStatus(int $code, string $reasonPhrase = ''): PsrResponseInterface
    {
        return $this
            ->with('StatusCode', $this->filterStatusCode($code))
            ->with('ReasonPhrase', $this->filterReasonPhrase($code, $reasonPhrase));
    }

    private function filterStatusCode(int $code): int
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException(
                sprintf('Invalid HTTP status code: %d', $code),
            );
        }
        return $code;
    }

    private function filterReasonPhrase(int $code, ?string $reasonPhrase): ?string
    {
        return Str::coalesce($reasonPhrase, null)
            ?? static::STATUS_CODE[$code]
            ?? null;
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

    /**
     * @return array{status:int,statusText:string,httpVersion:string,cookies:array<array{name:string,value:string,path?:string,domain?:string,expires?:string,httpOnly?:bool,secure?:bool}>,headers:array<array{name:string,value:string}>,content:array{size:int,mimeType:string,text:string},redirectURL:string,headersSize:int,bodySize:int}
     */
    public function jsonSerialize(): array
    {
        $response = parent::jsonSerialize();

        $contentType = $this->Headers->getLastHeaderValue(self::HEADER_CONTENT_TYPE);
        $location = $this->Headers->getHeader(self::HEADER_LOCATION);

        return [
            'status' => $this->StatusCode,
            'statusText' => (string) $this->ReasonPhrase,
            'httpVersion' => $response['httpVersion'],
            'cookies' => $response['cookies'],
            'headers' => $response['headers'],
            'content' => [
                'size' => $response['bodySize'],
                'mimeType' => $contentType,
                'text' => (string) $this->Body,
            ],
            'redirectURL' => count($location) === 1 ? $location[0] : '',
        ] + $response;
    }
}
