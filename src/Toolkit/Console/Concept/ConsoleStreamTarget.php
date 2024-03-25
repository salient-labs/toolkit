<?php declare(strict_types=1);

namespace Salient\Console\Concept;

use Salient\Console\Support\ConsoleFormat as Format;
use Salient\Console\Support\ConsoleMessageFormat as MessageFormat;
use Salient\Console\Support\ConsoleMessageFormats as MessageFormats;
use Salient\Console\Support\ConsoleTagFormats as TagFormats;
use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Console\ConsoleMessageTypeGroup as MessageTypeGroup;
use Salient\Contract\Console\ConsoleTag as Tag;
use Salient\Contract\Console\ConsoleTargetStreamInterface;
use Salient\Contract\Core\EscapeSequence as Colour;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Core\MessageLevelGroup as LevelGroup;

/**
 * Base class for console output targets with an underlying PHP stream
 */
abstract class ConsoleStreamTarget extends ConsolePrefixTarget implements ConsoleTargetStreamInterface
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

    protected function createTagFormats(): TagFormats
    {
        if (!$this->isTty()) {
            return new TagFormats();
        }

        $bold = Format::ttyBold();
        $dim = Format::ttyDim();
        $boldCyan = Format::ttyBold(Colour::CYAN);
        $red = Format::ttyColour(Colour::RED);
        $green = Format::ttyColour(Colour::GREEN);
        $yellow = Format::ttyColour(Colour::YELLOW);
        $cyan = Format::ttyColour(Colour::CYAN);
        $yellowUnderline = Format::ttyUnderline(Colour::YELLOW);

        return (new TagFormats())
            ->set(Tag::HEADING, $boldCyan)
            ->set(Tag::BOLD, $bold)
            ->set(Tag::ITALIC, $yellow)
            ->set(Tag::UNDERLINE, $yellowUnderline)
            ->set(Tag::LOW_PRIORITY, $dim)
            ->set(Tag::CODE_SPAN, $bold)
            ->set(Tag::DIFF_HEADER, $bold)
            ->set(Tag::DIFF_RANGE, $cyan)
            ->set(Tag::DIFF_ADDITION, $green)
            ->set(Tag::DIFF_REMOVAL, $red);
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
            ->set(LevelGroup::ERRORS, MessageTypeGroup::ALL, new MessageFormat($boldRed, $default, $boldRed))
            ->set(Level::WARNING, MessageTypeGroup::ALL, new MessageFormat($boldYellow, $default, $boldYellow))
            ->set(Level::NOTICE, MessageTypeGroup::ALL, new MessageFormat($bold, $cyan, $boldCyan))
            ->set(Level::INFO, MessageTypeGroup::ALL, new MessageFormat($default, $yellow, $boldYellow))
            ->set(Level::DEBUG, MessageTypeGroup::ALL, new MessageFormat($dim, $dim, $boldDim))
            ->set(LevelGroup::INFO, MessageType::SUCCESS, new MessageFormat($green, $default, $boldGreen));
    }
}
