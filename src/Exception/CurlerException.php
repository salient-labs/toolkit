<?php

declare(strict_types=1);

namespace Lkrms\Exception;

use Lkrms\Curler\Curler;
use Lkrms\Util\Format;
use Throwable;

/**
 * Thrown when a Curler request fails
 *
 */
class CurlerException extends \Lkrms\Exception\Exception
{
    /**
     * @var array|null
     */
    protected $CurlInfo;

    /**
     * @var string|array|null
     */
    protected $RequestData;

    /**
     * @var int|null
     */
    protected $ResponseCode;

    /**
     * @var array|null
     */
    protected $ResponseHeaders;

    /**
     * @var string|null
     */
    protected $Response;

    public function __construct(Curler $curler, string $message, int $code = 0, Throwable $previous = null)
    {
        $this->CurlInfo        = $curler->getCurlInfo();
        $this->RequestData     = $curler->getLastRequestData();
        $this->ResponseCode    = $curler->getLastResponseCode();
        $this->ResponseHeaders = $curler->getLastResponseHeaders();

        if ($curler->getDebug())
        {
            $this->Response = $curler->getLastResponse() ?: "";
        }

        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        $string   = [];
        $string[] = parent::__toString();
        $string[] = implode("\n", [
            "Response:",
            Format::array($this->ResponseHeaders) ?: "<no headers>",
            is_null($this->Response) ? "<response body not available>" : ($this->Response ?: "<empty response body>"),
        ]);
        $string[] = implode("\n", [
            "cURL info:",
            Format::array($this->CurlInfo)
        ]);
        $string[] = implode("\n", [
            "Request:",
            is_array($this->RequestData) ? Format::array($this->RequestData) : $this->RequestData
        ]);

        return implode("\n\n", $string);
    }

    public function getResponseCode(): ?int
    {
        return $this->ResponseCode;
    }

    public function getStatusLine(): ?string
    {
        return $this->ResponseHeaders["status"] ?? (string)$this->ResponseCode;
    }
}
