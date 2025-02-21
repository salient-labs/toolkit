<?php declare(strict_types=1);

namespace Salient\Console\Concept;

use Salient\Console\Support\ConsoleFormat as Format;
use Salient\Console\Support\ConsoleMessageFormat as MessageFormat;
use Salient\Console\Support\ConsoleMessageFormats as MessageFormats;
use Salient\Console\Support\ConsoleTagFormats as TagFormats;
use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Console\ConsoleMessageTypeGroup as MessageTypeGroup;
use Salient\Contract\Console\ConsoleTag as Tag;
use Salient\Contract\Console\ConsoleTargetStreamInterface;
use Salient\Contract\HasEscapeSequence;

/**
 * Base class for console output targets with an underlying PHP stream
 */
abstract class ConsoleStreamTarget extends ConsolePrefixTarget implements
    ConsoleTargetStreamInterface,
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
        $boldCyan = Format::ttyBold(self::CYAN_FG);
        $red = Format::ttyColour(self::RED_FG);
        $green = Format::ttyColour(self::GREEN_FG);
        $yellow = Format::ttyColour(self::YELLOW_FG);
        $cyan = Format::ttyColour(self::CYAN_FG);
        $yellowUnderline = Format::ttyUnderline(self::YELLOW_FG);

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
        $boldRed = Format::ttyBold(self::RED_FG);
        $boldGreen = Format::ttyBold(self::GREEN_FG);
        $boldYellow = Format::ttyBold(self::YELLOW_FG);
        $boldMagenta = Format::ttyBold(self::MAGENTA_FG);
        $boldCyan = Format::ttyBold(self::CYAN_FG);
        $green = Format::ttyColour(self::GREEN_FG);
        $yellow = Format::ttyColour(self::YELLOW_FG);
        $cyan = Format::ttyColour(self::CYAN_FG);

        return (new MessageFormats())
            ->set(Console::LEVELS_ERRORS, MessageTypeGroup::ALL, new MessageFormat($boldRed, $default, $boldRed))
            ->set(Console::LEVEL_WARNING, MessageTypeGroup::ALL, new MessageFormat($yellow, $default, $boldYellow))
            ->set(Console::LEVEL_NOTICE, MessageTypeGroup::ALL, new MessageFormat($bold, $cyan, $boldCyan))
            ->set(Console::LEVEL_INFO, MessageTypeGroup::ALL, new MessageFormat($default, $yellow, $yellow))
            ->set(Console::LEVEL_DEBUG, MessageTypeGroup::ALL, new MessageFormat($dim, $dim, $dim))
            ->set(Console::LEVELS_INFO, MessageType::PROGRESS, new MessageFormat($default, $yellow, $yellow))
            ->set(Console::LEVELS_INFO, MessageTypeGroup::GROUP, new MessageFormat($boldMagenta, $default, $boldMagenta))
            ->set(Console::LEVELS_INFO, MessageType::SUMMARY, new MessageFormat($default, $default, $bold))
            ->set(Console::LEVELS_INFO, MessageType::SUCCESS, new MessageFormat($green, $default, $boldGreen))
            ->set(Console::LEVELS_ERRORS_AND_WARNINGS, MessageType::FAILURE, new MessageFormat($yellow, $default, $boldYellow));
    }
}
