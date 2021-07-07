<?php

declare(strict_types=1);

namespace Lkrms\Curler;
use Exception;

class CurlerException extends Exception
{
    protected $curler;

    public function __construct(string $message, Curler $curler, $code = 0, $previous = null)
    {
        $this->curler = $curler;
        parent::__construct($message, $code, $previous);
    }

    private static function GetArrayAsString(string $entity, ? array $arr)
    {
        if (empty($arr))
        {
            return '';
        }

        $s = "\n$entity:";

        foreach ($arr as $k => $v)
        {
            $s .= "\n{$k} " . var_export($v, true);
        }

        return $s;
    }

    public function __toString()
    {
        $curlerDetail  = '';
        $curlerDetail .= self::GetArrayAsString('cURL info', $this->curler->GetLastCurlInfo());
        $curlerDetail .= self::GetArrayAsString('Response headers', $this->curler->GetLastResponseHeaders());

        if ($this->curler->GetDebug() && ! is_null($this->curler->GetLastResponse()))
        {
            $curlerDetail .= "\nResponse:\n" . $this->curler->GetLastResponse();
        }

        return __CLASS__ . ": {$this->message} in {$this->file}:{$this->line}" . $curlerDetail;
    }
}

