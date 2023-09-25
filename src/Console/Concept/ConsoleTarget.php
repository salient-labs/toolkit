<?php declare(strict_types=1);

namespace Lkrms\Console\Concept;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleLevels as Levels;
use Lkrms\Console\Catalog\ConsoleMessageType as Type;
use Lkrms\Console\Catalog\ConsoleMessageTypes as Types;
use Lkrms\Console\Catalog\ConsoleTag as Tag;
use Lkrms\Console\Contract\IConsoleTargetWithPrefix;
use Lkrms\Console\Support\ConsoleFormat as Format;
use Lkrms\Console\Support\ConsoleMessageFormat as MessageFormat;
use Lkrms\Console\Support\ConsoleMessageFormats as MessageFormats;
use Lkrms\Console\Support\ConsoleTagFormats as TagFormats;
use Lkrms\Console\ConsoleFormatter as Formatter;
use Lkrms\Support\Catalog\TtyControlSequence as Colour;

/**
 * Recommended base class for console output targets
 */
abstract class ConsoleTarget implements IConsoleTargetWithPrefix
{
    private ?string $Prefix = null;

    private int $PrefixLength = 0;

    private Formatter $Formatter;

    /**
     * @param Level::* $level
     * @param mixed[] $context
     */
    abstract protected function writeToTarget($level, string $message, array $context): void;

    /**
     * @param Level::* $level
     */
    final public function write($level, string $message, array $context = []): void
    {
        $this->writeToTarget(
            $level,
            $this->Prefix === null
                ? $message
                : $this->Prefix . str_replace("\n", "\n{$this->Prefix}", $message),
            $context
        );
    }

    final public function setPrefix(?string $prefix): void
    {
        $this->Prefix =
            ($prefix ?? '') === ''
                ? null
                : $this->getFormatter()
                    ->getTagFormat(Tag::LOW_PRIORITY)
                    ->apply($prefix);
        $this->PrefixLength = strlen($this->Prefix ?: '');
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

    public function getFormatter(): Formatter
    {
        return $this->Formatter
            ?? ($this->Formatter = new Formatter(
                $this->createTagFormats(),
                $this->createMessageFormats(),
                fn(): ?int => $this->width(),
            ));
    }

    public function width(): ?int
    {
        return 80 - $this->PrefixLength;
    }

    protected function createTagFormats(): TagFormats
    {
        if (!$this->isTty()) {
            return new TagFormats();
        }

        $bold = Format::ttyBold();
        $dim = Format::ttyDim();
        $boldCyan = Format::ttyBold(Colour::CYAN);
        $yellow = Format::ttyColour(Colour::YELLOW);
        $yellowUnderline = Format::ttyUnderline(Colour::YELLOW);

        return (new TagFormats())
            ->set(Tag::HEADING, $boldCyan)
            ->set(Tag::BOLD, $bold)
            ->set(Tag::ITALIC, $yellow)
            ->set(Tag::UNDERLINE, $yellowUnderline)
            ->set(Tag::LOW_PRIORITY, $dim)
            ->set(Tag::CODE_SPAN, $bold);
    }

    protected function createMessageFormats(): MessageFormats
    {
        if (!$this->isTty()) {
            return new MessageFormats();
        }

        $default = Format::getDefaultFormat();
        $bold = Format::ttyBold();
        $dim = Format::ttyDim();
        $boldDim = Format::ttyBoldDim();
        $boldRed = Format::ttyBold(Colour::RED);
        $boldGreen = Format::ttyBold(Colour::GREEN);
        $boldYellow = Format::ttyBold(Colour::YELLOW);
        $boldCyan = Format::ttyBold(Colour::CYAN);
        $green = Format::ttyColour(Colour::GREEN);
        $yellow = Format::ttyColour(Colour::YELLOW);
        $cyan = Format::ttyColour(Colour::CYAN);

        return (new MessageFormats())
            ->set(Levels::ERRORS, Types::ALL, new MessageFormat($boldRed, $default, $boldRed))
            ->set(Level::WARNING, Types::ALL, new MessageFormat($boldYellow, $default, $boldYellow))
            ->set(Level::NOTICE, Types::ALL, new MessageFormat($bold, $cyan, $boldCyan))
            ->set(Level::INFO, Types::ALL, new MessageFormat($default, $yellow, $boldYellow))
            ->set(Level::DEBUG, Types::ALL, new MessageFormat($dim, $dim, $boldDim))
            ->set(Levels::INFO, Type::SUCCESS, new MessageFormat($green, $default, $boldGreen));
    }
}
