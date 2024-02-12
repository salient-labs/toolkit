<?php declare(strict_types=1);

namespace Lkrms\Http;

use Lkrms\Http\Contract\HttpHeadersInterface;
use Lkrms\Utility\Arr;
use Salient\Core\Concern\ReadsProtectedProperties;
use Salient\Core\Contract\Readable;

/**
 * An HTTP response
 *
 * @property-read string $ProtocolVersion
 * @property-read int $StatusCode
 * @property-read string|null $ReasonPhrase
 * @property-read HttpHeadersInterface $Headers
 * @property-read string|null $Body
 */
final class HttpResponse implements Readable
{
    use ReadsProtectedProperties;

    /**
     * @var string
     */
    protected $ProtocolVersion;

    /**
     * @var int
     */
    protected $StatusCode;

    /**
     * @var string|null
     */
    protected $ReasonPhrase;

    /**
     * @var HttpHeadersInterface
     */
    protected $Headers;

    /**
     * @var string|null
     */
    protected $Body;

    public function __construct(
        ?string $body,
        int $statusCode = 200,
        ?string $reasonPhrase = null,
        ?HttpHeadersInterface $headers = null,
        string $protocolVersion = '1.1'
    ) {
        $headers = $headers ?: new HttpHeaders();

        $this->Body = $body;
        $this->StatusCode = $statusCode;
        $this->ReasonPhrase = $reasonPhrase;
        $this->Headers = $headers->set('Content-Length', (string) strlen($this->Body));
        $this->ProtocolVersion = $protocolVersion;
    }

    public function __toString(): string
    {
        $response = [
            Arr::implode(' ', [
                sprintf('HTTP/%s', $this->ProtocolVersion ?: '1.1'),
                $this->StatusCode,
                $this->ReasonPhrase,
            ])
        ];
        array_push($response, ...$this->Headers->getLines());
        $response[] = '';
        $response[] = $this->Body ?: '';

        return implode("\r\n", $response);
    }
}
