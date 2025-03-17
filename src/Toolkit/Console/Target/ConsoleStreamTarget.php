<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Salient\Console\Format\ConsoleFormat;
use Salient\Contract\Console\Format\FormatterInterface;
use Salient\Contract\Console\Target\StreamTargetInterface;
use Salient\Contract\HasEscapeSequence;

/**
 * Base class for console output targets with an underlying PHP stream
 */
abstract class ConsoleStreamTarget extends ConsolePrefixTarget implements
    StreamTargetInterface,
    HasEscapeSequence
{
    /**
     * @inheritDoc
     */
    public function isStdout(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isStderr(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isTty(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    protected function createFormatter(): FormatterInterface
    {
        return $this->isTty()
            ? ConsoleFormat::getFormatter($this)
            : parent::createFormatter();
    }
}
