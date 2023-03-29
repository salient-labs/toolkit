<?php declare(strict_types=1);

namespace Lkrms\Curler\Exception;

use Lkrms\Curler\Curler;
use Lkrms\Facade\Env;
use Lkrms\Facade\Format;

/**
 * Thrown when a Curler request fails
 *
 */
class CurlerException extends \Lkrms\Exception\Exception
{
    /**
     * @var Curler
     */
    protected $Curler;

    public function __construct(Curler $curler, string $message)
    {
        parent::__construct($message);

        // Save a clone of the Curler instance
        $this->Curler = $curler->withCurlInfo();
    }

    public function getDetail(): array
    {
        $detail = [
            'Response' => implode("\n", [
                Format::array($this->Curler->ResponseHeadersByName ?: []) ?: '<no headers>',
                is_null($this->Curler->ResponseBody)
                    ? '<no body>'
                    : ($this->Curler->ResponseBody ?: '<empty body>'),
            ]),
        ];

        if (Env::debug()) {
            $detail['Request'] =
                is_array($this->Curler->Body)
                    ? Format::array($this->Curler->Body)
                    : (string) $this->Curler->Body;

            $detail['curl_getinfo'] =
                is_null($this->Curler->CurlInfo)
                    ? ''
                    : Format::array(array_map(
                        fn($value) => is_string($value) ? trim($value) : $value,
                        $this->Curler->CurlInfo
                    ));
        }

        return $detail;
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
