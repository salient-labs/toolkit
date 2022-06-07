<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Core\Contract\IGettable;
use Lkrms\Core\Mixin\TFullyGettable;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Util\Convert;

/**
 *
 * @property-read string $Version
 * @property-read int $StatusCode
 * @property-read string|null $ReasonPhrase
 * @property-read CurlerHeaders $Headers
 * @property-read string|null $Body
 */
final class HttpResponse implements IGettable
{
    use TFullyGettable;

    /**
     * @internal
     * @var string
     */
    protected $Version;

    /**
     * @internal
     * @var int
     */
    protected $StatusCode;

    /**
     * @internal
     * @var string|null
     */
    protected $ReasonPhrase;

    /**
     * @internal
     * @var CurlerHeaders
     */
    protected $Headers;

    /**
     * @internal
     * @var string|null
     */
    protected $Body;

    public function __construct(
        ?string $body,
        int $statusCode        = 200,
        string $reasonPhrase   = null,
        CurlerHeaders $headers = null,
        string $version        = "HTTP/1.1"
    ) {
        $this->Body         = $body;
        $this->StatusCode   = $statusCode;
        $this->ReasonPhrase = $reasonPhrase;
        $this->Headers      = $headers ?: new CurlerHeaders();
        $this->Version      = $version;

        $this->Headers->setHeader("Content-Length", (string)strlen($this->Body));
    }

    public function getResponse(): string
    {
        $response = [Convert::sparseToString(" ", [
            $this->Version ?: "HTTP/1.1",
            $this->StatusCode,
            $this->ReasonPhrase
        ])];
        array_push($response, ...$this->Headers->getHeaders());
        $response[] = "";
        $response[] = $this->Body ?: "";

        return implode("\r\n", $response);
    }
}
