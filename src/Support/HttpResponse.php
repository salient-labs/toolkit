<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Facade\Convert;

/**
 *
 * @property-read string $Version
 * @property-read int $StatusCode
 * @property-read string|null $ReasonPhrase
 * @property-read ICurlerHeaders $Headers
 * @property-read string|null $Body
 */
final class HttpResponse implements IReadable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $Version;

    /**
     * @var int
     */
    protected $StatusCode;

    /**
     * @var string|null
     */
    protected $ReasonPhrase;

    /**
     * @var ICurlerHeaders
     */
    protected $Headers;

    /**
     * @var string|null
     */
    protected $Body;

    public function __construct(?string $body, int $statusCode = 200, string $reasonPhrase = null, ICurlerHeaders $headers = null, string $version = 'HTTP/1.1')
    {
        $this->Body         = $body;
        $this->StatusCode   = $statusCode;
        $this->ReasonPhrase = $reasonPhrase;
        $this->Headers      = $headers ?: new CurlerHeaders();
        $this->Version      = $version;

        $this->Headers->setHeader('Content-Length', (string) strlen($this->Body));
    }

    public function getResponse(): string
    {
        $response = [
            Convert::sparseToString(
                ' ',
                [
                    $this->Version ?: 'HTTP/1.1',
                    $this->StatusCode,
                    $this->ReasonPhrase,
                ]
            )
        ];
        array_push($response, ...$this->Headers->getHeaders());
        $response[] = '';
        $response[] = $this->Body ?: '';

        return implode("\r\n", $response);
    }
}
