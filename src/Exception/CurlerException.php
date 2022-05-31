<?php

declare(strict_types=1);

namespace Lkrms\Exception;

use Lkrms\Curler\Curler;
use Lkrms\Util\Env;
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
     * @var array|null
     */
    protected $ResponseHeaders;

    /**
     * @var string|null
     */
    protected $ResponseData;

    /**
     * @var int|null
     */
    protected $ResponseCode;

    /**
     * @var string|null
     */
    protected $ResponseStatus;

    public function __construct(
        Curler $curler,
        string $message,
        int $code = 0,
        Throwable $previous = null
    ) {
        $this->CurlInfo        = $curler->getCurlInfo();
        $this->RequestData     = $curler->Data;
        $this->ResponseHeaders = $curler->ResponseHeadersByName;
        $this->ResponseData    = $curler->ResponseData;
        $this->ResponseCode    = $curler->ResponseCode;
        $this->ResponseStatus  = $curler->ResponseStatus;

        parent::__construct($message, $code, $previous);
    }

    public function getDetail(): array
    {
        $detail = [
            "Response" => implode("\n", [
                Format::array($this->ResponseHeaders) ?: "<no headers>",
                (is_null($this->ResponseData)
                    ? "<no response body>"
                    : ($this->ResponseData ?: "<empty response body>")),
            ]),
        ];
        if (Env::debug())
        {
            $detail["Request"] = (is_array($this->RequestData)
                ? Format::array($this->RequestData)
                : (string)$this->RequestData);
            $detail["curl_getinfo"] = Format::array(array_map(
                fn($value) => is_string($value) ? trim($value) : $value,
                $this->CurlInfo
            ));
        }
        return $detail;
    }

    public function getResponseCode(): ?int
    {
        return $this->ResponseCode;
    }

    public function getResponseStatus(): ?string
    {
        return $this->ResponseStatus;
    }
}
