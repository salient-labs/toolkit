<?php

declare(strict_types=1);

namespace Lkrms\Console;

/**
 * Character sequences that can be added before and after text to change its
 * appearance
 *
 */
final class ConsoleFormat
{
    private $Before;

    private $After;

    public function __construct(string $before = "", string $after = "")
    {
        $this->Before = $before;
        $this->After  = $after;
    }

    public function apply(?string $text): string
    {
        if (!$text)
        {
            return "";
        }
        return "{$this->Before}$text{$this->After}";
    }
}
