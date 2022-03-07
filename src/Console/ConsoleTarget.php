<?php

declare(strict_types=1);

namespace Lkrms\Console;

/**
 *
 * @package Lkrms
 */
abstract class ConsoleTarget
{
    private $Prefix;

    abstract protected function WriteToTarget(int $level, string $message, array $context);

    public function Write($message, array $context = [], int $level = ConsoleLevel::INFO): void
    {
        $this->WriteToTarget(
            $level,
            ($this->Prefix
                ? $this->Prefix . str_replace("\n", "\n{$this->Prefix}", $message)
                : $message),
            $context
        );
    }

    public function SetPrefix(?string $prefix): void
    {
        $this->Prefix = $prefix;
    }

    public function IsStdout(): bool
    {
        return false;
    }

    public function IsStderr(): bool
    {
        return false;
    }

    public function IsTty(): bool
    {
        return false;
    }
}

