<?php declare(strict_types=1);

namespace Salient\Console\Concept;

use Salient\Console\Support\ConsoleFormat as Format;
use Salient\Console\Support\ConsoleMessageFormat as MessageFormat;
use Salient\Console\Support\ConsoleMessageFormats as MessageFormats;
use Salient\Console\Support\ConsoleTagFormats as TagFormats;
use Salient\Contract\Catalog\HasAnsiEscapeSequence;
use Salient\Contract\Catalog\MessageLevelGroup as LevelGroup;
use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Console\ConsoleMessageTypeGroup as MessageTypeGroup;
use Salient\Contract\Console\ConsoleTag as Tag;
use Salient\Contract\Console\ConsoleTargetStreamInterface;

/**
 * Base class for console output targets with an underlying PHP stream
 */
abstract class ConsoleStreamTarget extends ConsolePrefixTarget implements
    ConsoleTargetStreamInterface,
    HasAnsiEscapeSequence
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
    public function getEol(): string
    {
        return "\n";
    }

    protected function createTagFormats(): TagFormats
    {
        if (!$this->isTty()) {
            return new TagFormats();
        }

        $bold = Format::ttyBold();
        $dim = Format::ttyDim();
        $boldCyan = Format::ttyBold(self::CYAN);
        $red = Format::ttyColour(self::RED);
        $green = Format::ttyColour(self::GREEN);
        $yellow = Format::ttyColour(self::YELLOW);
        $cyan = Format::ttyColour(self::CYAN);
        $yellowUnderline = Format::ttyUnderline(self::YELLOW);

        return (new TagFormats())
            ->withFormat(Tag::HEADING, $boldCyan)
            ->withFormat(Tag::BOLD, $bold)
            ->withFormat(Tag::ITALIC, $yellow)
            ->withFormat(Tag::UNDERLINE, $yellowUnderline)
            ->withFormat(Tag::LOW_PRIORITY, $dim)
            ->withFormat(Tag::CODE_SPAN, $bold)
            ->withFormat(Tag::DIFF_HEADER, $bold)
            ->withFormat(Tag::DIFF_RANGE, $cyan)
            ->withFormat(Tag::DIFF_ADDITION, $green)
            ->withFormat(Tag::DIFF_REMOVAL, $red);
    }

    protected function createMessageFormats(): MessageFormats
    {
        if (!$this->isTty()) {
            return new MessageFormats();
        }

        $default = Format::getDefaultFormat();
        $bold = Format::ttyBold();
        $dim = Format::ttyDim();
        $boldRed = Format::ttyBold(self::RED);
        $boldGreen = Format::ttyBold(self::GREEN);
        $boldYellow = Format::ttyBold(self::YELLOW);
        $boldMagenta = Format::ttyBold(self::MAGENTA);
        $boldCyan = Format::ttyBold(self::CYAN);
        $green = Format::ttyColour(self::GREEN);
        $yellow = Format::ttyColour(self::YELLOW);
        $cyan = Format::ttyColour(self::CYAN);

        return (new MessageFormats())
            ->set(LevelGroup::ERRORS, MessageTypeGroup::ALL, new MessageFormat($boldRed, $default, $boldRed))
            ->set(Console::LEVEL_WARNING, MessageTypeGroup::ALL, new MessageFormat($yellow, $default, $boldYellow))
            ->set(Console::LEVEL_NOTICE, MessageTypeGroup::ALL, new MessageFormat($bold, $cyan, $boldCyan))
            ->set(Console::LEVEL_INFO, MessageTypeGroup::ALL, new MessageFormat($default, $yellow, $yellow))
            ->set(Console::LEVEL_DEBUG, MessageTypeGroup::ALL, new MessageFormat($dim, $dim, $dim))
            ->set(LevelGroup::INFO, MessageType::PROGRESS, new MessageFormat($default, $yellow, $yellow))
            ->set(LevelGroup::INFO, MessageTypeGroup::GROUP, new MessageFormat($boldMagenta, $default, $boldMagenta))
            ->set(LevelGroup::INFO, MessageType::SUMMARY, new MessageFormat($default, $default, $bold))
            ->set(LevelGroup::INFO, MessageType::SUCCESS, new MessageFormat($green, $default, $boldGreen))
            ->set(LevelGroup::ERRORS_AND_WARNINGS, MessageType::FAILURE, new MessageFormat($yellow, $default, $boldYellow));
    }
}
