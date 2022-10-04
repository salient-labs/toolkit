<?php

declare(strict_types=1);

namespace Lkrms\Console;

/**
 * A format that can be applied to part of a console message
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
