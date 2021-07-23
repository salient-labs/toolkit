<?php

declare(strict_types=1);

namespace Lkrms\Curler;

class CurlerHeader
{
    private $Headers = array(
        "user-agent" => "User-Agent:util-php/Curler (https://github.com/lkrms/util-php)"
    );

    public function SetHeader(string $name, string $value)
    {
        // HTTP headers are case-insensitive, so make sure we don't end up with duplicates
        $this->Headers[strtolower($name)] = "{$name}:{$value}";
    }

    public function UnsetHeader(string $name)
    {
        if (isset($this->Headers[$name]))
        {
            unset($this->Headers[$name]);
        }
    }

    public function GetHeaders(): array
    {
        return array_values($this->Headers);
    }
}

