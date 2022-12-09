<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Curler\CurlerHeaders;

/**
 *
 * @property-read string $Method
 * @property-read string $Target
 * @property-read string $Version
 * @property-read CurlerHeaders $Headers
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
    protected $Version;

    /**
     * @var CurlerHeaders
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

    public function __construct(string $method, string $target, string $version, CurlerHeaders $headers, ?string $body, string $client = null)
    {
        $this->Method  = $method;
        $this->Target  = $target;
        $this->Version = $version;
        $this->Headers = $headers;
        $this->Body    = $body;
        $this->Client  = $client;
    }
}
