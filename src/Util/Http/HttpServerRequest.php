<?php declare(strict_types=1);

namespace Lkrms\Http;

use Lkrms\Http\Contract\HttpHeadersInterface;
use Salient\Core\Concern\ReadsProtectedProperties;
use Salient\Core\Contract\Readable;

/**
 * An HTTP request
 *
 * @property-read string $Method
 * @property-read string $Target
 * @property-read string $ProtocolVersion
 * @property-read HttpHeadersInterface $Headers
 * @property-read string|null $Body
 * @property-read string|null $Client
 */
final class HttpServerRequest implements Readable
{
    use ReadsProtectedProperties;

    /**
     * @var string
     */
    protected $Method;

    /**
     * @var string
     */
    protected $Target;

    /**
     * @var string
     */
    protected $ProtocolVersion;

    /**
     * @var HttpHeadersInterface
     */
    protected $Headers;

    /**
     * @var string|null
     */
    protected $Body;

    /**
     * @var string|null
     */
    protected $Client;

    public function __construct(
        string $method,
        string $target,
        string $protocolVersion,
        HttpHeadersInterface $headers,
        ?string $body,
        ?string $client = null
    ) {
        $this->Method = $method;
        $this->Target = $target;
        $this->ProtocolVersion = $protocolVersion;
        $this->Headers = $headers;
        $this->Body = $body;
        $this->Client = $client;
    }
}
