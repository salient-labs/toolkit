<?php declare(strict_types=1);

namespace Lkrms\Console\Concept;

use Lkrms\Console\ConsoleFormat;
use Lkrms\Console\ConsoleFormatter;
use Lkrms\Console\ConsoleLevel as Level;
use Lkrms\Console\ConsoleMessageFormat;
use Lkrms\Console\ConsoleTag as Tag;
use Lkrms\Support\Dictionary\TtyControlSequence as Colour;
use UnexpectedValueException;

/**
 * Base class for console message targets
 *
 */
abstract class ConsoleTarget
{
    /**
     * True if formatting based on message level is enabled
     *
     * @var bool
     */
    protected $MessageFormatting = true;

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

    abstract protected function writeToTarget(int $level, string $message, array $context): void;

    final public function write(int $level, string $message, array $context): void
    {
        $this->writeToTarget(
            $level,
            $this->Prefix
                ? $this->Prefix . str_replace("\n", "\n{$this->Prefix}", $message)
                : $message,
            $context
        );
    }

    final public function setMessageFormatting(bool $messageFormatting): void
    {
        if ($this->MessageFormatting !== $messageFormatting) {
            $this->MessageFormatting = $messageFormatting;
            $this->MessageFormats = [];
            $this->Formatter = null;
        }
    }

    final public function setPrefix(?string $prefix): void
    {
        $this->Prefix =
            $prefix
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

    protected function createMessageFormat(int $level): ConsoleMessageFormat
    {
        if (!$this->MessageFormatting || !$this->isTty()) {
            $fmt = new ConsoleFormat();

            return new ConsoleMessageFormat($fmt, $fmt, $fmt);
        }

        switch ($level) {
            case Level::DEBUG:
                return new ConsoleMessageFormat(
                    new ConsoleFormat(Colour::DIM, Colour::UNBOLD_UNDIM, [Colour::UNBOLD_UNDIM => Colour::UNBOLD_DIM]),
                    new ConsoleFormat(Colour::DIM, Colour::UNBOLD_UNDIM, [Colour::UNBOLD_UNDIM => Colour::UNBOLD_DIM]),
                    new ConsoleFormat(Colour::BOLD . Colour::DIM, Colour::UNBOLD_UNDIM)
                );
            case Level::INFO:
                return new ConsoleMessageFormat(
                    new ConsoleFormat(),
                    new ConsoleFormat(Colour::YELLOW, Colour::DEFAULT),
                    new ConsoleFormat(Colour::BOLD . Colour::YELLOW, Colour::DEFAULT . Colour::UNBOLD_UNDIM)
                );
            case Level::NOTICE:
                return new ConsoleMessageFormat(
                    new ConsoleFormat(Colour::BOLD, Colour::UNBOLD_UNDIM),
                    new ConsoleFormat(Colour::CYAN, Colour::DEFAULT),
                    new ConsoleFormat(Colour::BOLD . Colour::CYAN, Colour::DEFAULT . Colour::UNBOLD_UNDIM)
                );
            case Level::WARNING:
                return new ConsoleMessageFormat(
                    new ConsoleFormat(Colour::BOLD . Colour::YELLOW, Colour::DEFAULT . Colour::UNBOLD_UNDIM),
                    new ConsoleFormat(),
                    new ConsoleFormat(Colour::BOLD . Colour::YELLOW, Colour::DEFAULT . Colour::UNBOLD_UNDIM)
                );
            case Level::ERROR:
            case Level::CRITICAL:
            case Level::ALERT:
            case Level::EMERGENCY:
                return new ConsoleMessageFormat(
                    new ConsoleFormat(Colour::BOLD . Colour::RED, Colour::DEFAULT . Colour::UNBOLD_UNDIM),
                    new ConsoleFormat(),
                    new ConsoleFormat(Colour::BOLD . Colour::RED, Colour::DEFAULT . Colour::UNBOLD_UNDIM)
                );
        }

        throw new UnexpectedValueException("Invalid ConsoleLevel: $level");
    }

    protected function createTagFormat(int $tag): ConsoleFormat
    {
        if (!$this->isTty()) {
            return new ConsoleFormat();
        }

        switch ($tag) {
            case Tag::HEADING:
                return new ConsoleFormat(Colour::BOLD . Colour::CYAN, Colour::DEFAULT . Colour::UNBOLD_UNDIM);
            case Tag::BOLD:
                return new ConsoleFormat(Colour::BOLD, Colour::UNBOLD_UNDIM);
            case Tag::ITALIC:
                return new ConsoleFormat(Colour::YELLOW, Colour::DEFAULT);
            case Tag::UNDERLINE:
                return new ConsoleFormat(Colour::YELLOW . Colour::UNDERLINE, Colour::NO_UNDERLINE . Colour::DEFAULT);
            case Tag::LOW_PRIORITY:
                return new ConsoleFormat(Colour::DIM, Colour::UNBOLD_UNDIM, [Colour::UNBOLD_UNDIM => Colour::UNBOLD_DIM]);
        }

        throw new UnexpectedValueException("Invalid ConsoleTextTag: $tag");
    }

    final public function getMessageFormat(int $level): ConsoleMessageFormat
    {
        return $this->MessageFormats[$level]
            ?? ($this->MessageFormats[$level] = $this->createMessageFormat($level));
    }

    final public function getTagFormat(int $tag): ConsoleFormat
    {
        return $this->TagFormats[$tag]
            ?? ($this->TagFormats[$tag] = $this->createTagFormat($tag));
    }

    final public function getFormatter(): ConsoleFormatter
    {
        return $this->Formatter
            ?: ($this->Formatter = new ConsoleFormatter($this));
    }
}
