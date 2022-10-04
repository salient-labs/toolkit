<?php

declare(strict_types=1);

namespace Lkrms\Console\ConsoleTarget;

use Lkrms\Console\ConsoleColour;
use Lkrms\Console\ConsoleLevel;

/**
 * Base class for console message targets
 *
 */
abstract class ConsoleTarget
{
    /**
     * @var string
     */
    private $Prefix;

    abstract protected function writeToTarget(int $level, string $message, array $context);

    final public function write($message, array $context = [], int $level = ConsoleLevel::INFO)
    {
        $this->writeToTarget(
            $level,
            ($this->Prefix
                ? $this->Prefix . str_replace("\n", "\n{$this->Prefix}", $message)
                : $message),
            $context
        );
    }

    final public function setPrefix(?string $prefix)
    {
        if ($prefix && $this->supportsColour())
        {
            $this->Prefix = ConsoleColour::DIM . $prefix . ConsoleColour::UNDIM;
        }
        else
        {
            $this->Prefix = $prefix;
        }
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

    public function supportsColour(): bool
    {
        return $this->isTty();
    }
}
