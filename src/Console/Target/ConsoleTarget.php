<?php

declare(strict_types=1);

namespace Lkrms\Console\Target;

use Lkrms\Console\ConsoleFormat;
use Lkrms\Console\ConsoleFormatter;
use Lkrms\Console\ConsoleLevel as Level;
use Lkrms\Console\ConsoleMessageFormat;
use Lkrms\Console\ConsoleTag as Tag;
use Lkrms\Support\TtyControlSequence as Colour;
use UnexpectedValueException;

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

    /**
     * Message level => format
     *
     * @var array<int,ConsoleMessageFormat>
     */
    private $MessageFormats = [];

    /**
     * Tag => format
     *
     * @var array<int,ConsoleFormat>
     */
    private $TagFormats = [];

    /**
     * @var ConsoleFormatter|null
     */
    private $Formatter;

    abstract protected function writeToTarget(int $level, string $message, array $context);

    final public function write(int $level, string $message, array $context)
    {
        $this->writeToTarget($level, $this->Prefix
            ? $this->Prefix . str_replace("\n", "\n{$this->Prefix}", $message)
            : $message, $context);
    }

    final public function setPrefix(?string $prefix)
    {
        $this->Prefix = $prefix
            ? $this->getTagFormat(Tag::LOW_PRIORITY)->apply($prefix)
            : $prefix;
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

    protected function _getMessageFormat(int $level): ConsoleMessageFormat
    {
        if (!$this->isTty())
        {
            $fmt = new ConsoleFormat();

            return new ConsoleMessageFormat($fmt, $fmt, $fmt);
        }

        switch ($level)
        {
            case Level::DEBUG:
                return new ConsoleMessageFormat(
                    new ConsoleFormat(Colour::DIM, Colour::UNDIM),
                    new ConsoleFormat(Colour::DIM, Colour::UNDIM),
                    new ConsoleFormat(Colour::BOLD . Colour::DIM, Colour::UNDIM . Colour::UNBOLD)
                );
            case Level::INFO:
                return new ConsoleMessageFormat(
                    new ConsoleFormat(),
                    new ConsoleFormat(Colour::YELLOW, Colour::DEFAULT),
                    new ConsoleFormat(Colour::BOLD . Colour::YELLOW, Colour::DEFAULT . Colour::UNBOLD)
                );
            case Level::NOTICE:
                return new ConsoleMessageFormat(
                    new ConsoleFormat(Colour::BOLD, Colour::UNBOLD),
                    new ConsoleFormat(Colour::CYAN, Colour::DEFAULT),
                    new ConsoleFormat(Colour::BOLD . Colour::CYAN, Colour::DEFAULT . Colour::UNBOLD)
                );
            case Level::WARNING:
                return new ConsoleMessageFormat(
                    new ConsoleFormat(Colour::BOLD . Colour::YELLOW, Colour::DEFAULT . Colour::UNBOLD),
                    new ConsoleFormat(),
                    new ConsoleFormat(Colour::BOLD . Colour::YELLOW, Colour::DEFAULT . Colour::UNBOLD)
                );
            case Level::ERROR:
            case Level::CRITICAL:
            case Level::ALERT:
            case Level::EMERGENCY:
                return new ConsoleMessageFormat(
                    new ConsoleFormat(Colour::BOLD . Colour::RED, Colour::DEFAULT . Colour::UNBOLD),
                    new ConsoleFormat(),
                    new ConsoleFormat(Colour::BOLD . Colour::RED, Colour::DEFAULT . Colour::UNBOLD)
                );
        }

        throw new UnexpectedValueException("Invalid ConsoleLevel: $level");
    }

    protected function _getTagFormat(int $tag): ConsoleFormat
    {
        if (!$this->isTty())
        {
            return new ConsoleFormat();
        }

        switch ($tag)
        {
            case Tag::HEADING:
                return new ConsoleFormat(Colour::BOLD . Colour::CYAN, Colour::DEFAULT . Colour::UNBOLD);
            case Tag::SUBHEADING:
                return new ConsoleFormat(Colour::BOLD, Colour::UNBOLD);
            case Tag::TITLE:
                return new ConsoleFormat(Colour::YELLOW, Colour::DEFAULT);
            case Tag::LOW_PRIORITY:
                return new ConsoleFormat(Colour::DIM, Colour::UNDIM);
        }

        throw new UnexpectedValueException("Invalid ConsoleTextTag: $tag");
    }

    final public function getMessageFormat(int $level): ConsoleMessageFormat
    {
        return $this->MessageFormats[$level]
            ?? ($this->MessageFormats[$level] = $this->_getMessageFormat($level));
    }

    final public function getTagFormat(int $tag): ConsoleFormat
    {
        return $this->TagFormats[$tag]
            ?? ($this->TagFormats[$tag] = $this->_getTagFormat($tag));
    }

    final public function getFormatter(): ConsoleFormatter
    {
        return $this->Formatter
            ?: ($this->Formatter = new ConsoleFormatter($this));
    }
}
