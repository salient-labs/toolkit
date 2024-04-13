<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Salient\Core\Utility\Format;
use Salient\Core\AbstractException;
use Salient\Curler\Curler;

/**
 * Base class for Curler exceptions
 */
abstract class CurlerException extends AbstractException
{
    /**
     * @var Curler
     */
    protected $Curler;

    public function __construct(string $message, Curler $curler)
    {
        $this->Curler = clone $curler;

        parent::__construct($message);
    }

    public function getMetadata(): array
    {
        return [
            'Response' =>
                implode("\n", [
                    Format::array($this->Curler->ResponseHeadersByName ?: []) ?: '<no headers>',
                    $this->Curler->ResponseBody === null
                        ? '<no body>'
                        : ($this->Curler->ResponseBody === ''
                            ? '<empty body>'
                            : $this->Curler->ResponseBody),
                ]),
            'Request' =>
                is_array($this->Curler->Body)
                    ? Format::array($this->Curler->Body)
                    : ($this->Curler->Body === null
                        ? '<no body>'
                        : ($this->Curler->Body === ''
                            ? '<empty body>'
                            : $this->Curler->Body)),
            'curl_getinfo' =>
                $this->Curler->CurlInfo === null
                    ? '<not available>'
                    : Format::array(array_map(
                        fn($value) => is_string($value) ? trim($value) : $value,
                        $this->Curler->CurlInfo,
                    )),
        ];
    }
}
