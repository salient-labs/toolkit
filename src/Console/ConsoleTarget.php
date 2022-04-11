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

    abstract protected function writeToTarget(int $level, string $message, array $context);

    public function write($message, array $context = [], int $level = ConsoleLevel::INFO): void
    {
        $this->writeToTarget(
            $level,
            ($this->Prefix
                ? $this->Prefix . str_replace("\n", "\n{$this->Prefix}", $message)
                : $message),
            $context
        );
    }

    public function setPrefix(?string $prefix): void
    {
        $this->Prefix = $prefix;
    }

    public function isStdout(): bool
    {
        return false;
    }

    public function isStderr(): bool
    {
        return false;
    }

    public function isTty(): bool
    {
        return false;
    }
}

