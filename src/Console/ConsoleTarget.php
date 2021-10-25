<?php

declare(strict_types=1);

namespace Lkrms\Console;

abstract class ConsoleTarget
{
    abstract protected function WriteToTarget(int $level, string $message, array $context);

    public function Write($message, array $context = [], int $level = ConsoleLevel::INFO)
    {
        $this->WriteToTarget($level, $message, $context);
    }
}

