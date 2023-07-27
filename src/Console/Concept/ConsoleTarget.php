<?php declare(strict_types=1);

namespace Lkrms\Console\Concept;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleTag as Tag;
use Lkrms\Console\ConsoleFormat;
use Lkrms\Console\ConsoleFormatter;
use Lkrms\Console\ConsoleMessageFormat;
use Lkrms\Console\ConsoleTagFormats;
use Lkrms\Support\Catalog\TtyControlSequence as Colour;
use UnexpectedValueException;

/**
 * Base class for console output targets
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
     * @var string|null
     */
    private $Prefix;

    /**
     * Message level => format
     *
     * @var array<int,ConsoleMessageFormat>
     */
    private $MessageFormats = [];

    private ConsoleTagFormats $TagFormats;

    /**
     * @var ConsoleFormatter|null
     */
    private $Formatter;

    abstract protected function writeToTarget(int $level, string $message, array $context): void;

    final public function write(int $level, string $message, array $context): void
    {
        $this->writeToTarget(
            $level,
            $this->Prefix === null
                ? $message
                : $this->Prefix . str_replace("\n", "\n{$this->Prefix}", $message),
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
            $prefix === null || $prefix === ''
                ? null
                : $this->getTagFormats()[Tag::LOW_PRIORITY]->apply($prefix);
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

    final public function getFormatter(): ConsoleFormatter
    {
        return $this->Formatter
            ?: ($this->Formatter = new ConsoleFormatter($this->getTagFormats()));
    }

    final public function getTagFormats(): ConsoleTagFormats
    {
        return $this->TagFormats
            ?? ($this->TagFormats = $this->createTagFormats());
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

    protected function createTagFormats(): ConsoleTagFormats
    {
        $formats = new ConsoleTagFormats();

        if (!$this->isTty()) {
            return $formats;
        }

        $formats[Tag::HEADING] =
            new ConsoleFormat(
                Colour::BOLD . Colour::CYAN,
                Colour::DEFAULT . Colour::UNBOLD_UNDIM,
                [
                    Colour::UNBOLD_UNDIM => Colour::UNDIM_BOLD,
                    Colour::DEFAULT => Colour::CYAN,
                ],
            );
        $formats[Tag::BOLD] =
            new ConsoleFormat(
                Colour::BOLD,
                Colour::UNBOLD_UNDIM,
                [
                    Colour::UNBOLD_UNDIM => Colour::UNDIM_BOLD,
                ],
            );
        $formats[Tag::ITALIC] =
            new ConsoleFormat(
                Colour::YELLOW,
                Colour::DEFAULT,
                [
                    Colour::DEFAULT => Colour::YELLOW,
                ],
            );
        $formats[Tag::UNDERLINE] =
            new ConsoleFormat(
                Colour::YELLOW . Colour::UNDERLINE,
                Colour::NO_UNDERLINE . Colour::DEFAULT,
                [
                    Colour::DEFAULT => Colour::YELLOW,
                    Colour::NO_UNDERLINE => '',
                ],
            );
        $formats[Tag::LOW_PRIORITY] =
            new ConsoleFormat(
                Colour::DIM,
                Colour::UNBOLD_UNDIM,
                [
                    Colour::UNBOLD_UNDIM => Colour::UNBOLD_DIM,
                ],
            );
        $formats[Tag::CODE_SPAN] = $formats[Tag::BOLD];

        return $formats;
    }

    final public function getMessageFormat(int $level): ConsoleMessageFormat
    {
        return $this->MessageFormats[$level]
            ?? ($this->MessageFormats[$level] = $this->createMessageFormat($level));
    }
}
