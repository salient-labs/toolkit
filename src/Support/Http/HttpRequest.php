<?php declare(strict_types=1);

namespace Lkrms\Support\Http;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Curler\Contract\ICurlerHeaders;

/**
 * Represents an incoming HTTP request
 *
 * @property-read string $Method
 * @property-read string $Target
 * @property-read string $ProtocolVersion
 * @property-read ICurlerHeaders $Headers
 * @property-read string|null $Body
 * @property-read string|null $Client
 */
final class HttpRequest implements IReadable
{
    use TFullyReadable;

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
     * @var ICurlerHeaders
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
        ICurlerHeaders $headers,
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
