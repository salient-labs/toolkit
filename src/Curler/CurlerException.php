<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use Exception;
use Lkrms\Convert;
use Throwable;

/**
 * Thrown when a cURL request fails
 *
 * @package Lkrms\Curler
 */
class CurlerException extends Exception
{
    /**
     * @var array
     */
    protected $CurlInfo;

    /**
     * @var array
     */
    protected $ResponseHeaders;

    /**
     * @var string
     */
    protected $Response;

    public function __construct(Curler $curler, string $message, int $code = 0, Throwable $previous = null)
    {
        $this->CurlInfo        = $curler->GetLastCurlInfo();
        $this->ResponseHeaders = $curler->GetLastResponseHeaders();

        if ($curler->GetDebug())
        {
            $this->Response = $curler->GetLastResponse() ?: "";
        }

        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        $string   = [];
        $string[] = parent::__toString();
        $string[] = implode("\n", [
            "Response:",
            Convert::ArrayToString($this->ResponseHeaders) ?: "<no headers>",
            is_null($this->Response) ? "<response body not available>" : ($this->Response ?: "<empty response body>"),
        ]);
        $string[] = implode("\n", [
            "cURL info:",
            Convert::ArrayToString($this->CurlInfo)
        ]);

        return implode("\n\n", $string);
    }
}

