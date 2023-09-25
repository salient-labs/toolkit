<?php declare(strict_types=1);

namespace Lkrms\Curler\Exception;

use Lkrms\Curler\Curler;
use Lkrms\Facade\Format;
use Throwable;

/**
 * Thrown when an HTTP request fails
 */
class CurlerException extends \Lkrms\Exception\Exception
{
    /**
     * @var Curler
     */
    protected $Curler;

    public function __construct(Curler $curler, string $message, ?Throwable $previous = null)
    {
        $this->Curler = clone $curler;

        parent::__construct($message, $previous);
    }

    public function getDetail(): array
    {
        return [
            'Response' => implode("\n", [
                Format::array($this->Curler->ResponseHeadersByName ?: []) ?: '<no headers>',
                is_null($this->Curler->ResponseBody)
                    ? '<no body>'
                    : ($this->Curler->ResponseBody ?: '<empty body>'),
            ]),
            'Request' => is_array($this->Curler->Body)
                ? Format::array($this->Curler->Body)
                : (string) $this->Curler->Body,
            'curl_getinfo' => is_null($this->Curler->CurlInfo)
                ? ''
                : Format::array(array_map(
                    fn($value) => is_string($value) ? trim($value) : $value,
                    $this->Curler->CurlInfo
                )),
        ];
    }

    public function getStatusCode(): ?int
    {
        return $this->Curler->StatusCode;
    }

    public function getReasonPhrase(): ?string
    {
        return $this->Curler->ReasonPhrase;
    }
}
