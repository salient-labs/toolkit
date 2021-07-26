<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use Exception;
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
            $this->Response = $curler->GetLastResponse();
        }

        parent::__construct($message, $code, $previous);
    }

    private function ArrayToString(string $name, ?array $array): string
    {
        if (is_null($array))
        {
            return "";
        }

        $string = "\n$name:";

        foreach ($array as $key => $value)
        {
            $string .= "\n$key " . var_export($value, true);
        }

        return $string;
    }

    public function __toString()
    {
        $string  = "";
        $string .= $this->ArrayToString("cURL info", $this->CurlInfo);
        $string .= $this->ArrayToString("Response headers", $this->ResponseHeaders);

        if ( ! is_null($this->Response))
        {
            $string .= "\nResponse:\n" . $this->Response;
        }

        return __CLASS__ . ": {$this->message} in {$this->file}:{$this->line}" . $string;
    }
}

