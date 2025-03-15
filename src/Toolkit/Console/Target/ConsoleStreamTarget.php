<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Salient\Console\Format\ConsoleFormat as Format;
use Salient\Console\Format\ConsoleMessageFormat as MessageFormat;
use Salient\Console\Format\ConsoleMessageFormats as MessageFormats;
use Salient\Console\Format\ConsoleTagFormats as TagFormats;
use Salient\Contract\Console\Target\StreamTargetInterface;
use Salient\Contract\Console\ConsoleInterface as Console;
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
            ->withFormat(Format::TAG_HEADING, $boldCyan)
            ->withFormat(Format::TAG_BOLD, $bold)
            ->withFormat(Format::TAG_ITALIC, $yellow)
            ->withFormat(Format::TAG_UNDERLINE, $yellowUnderline)
            ->withFormat(Format::TAG_LOW_PRIORITY, $dim)
            ->withFormat(Format::TAG_CODE_SPAN, $bold)
            ->withFormat(Format::TAG_DIFF_HEADER, $bold)
            ->withFormat(Format::TAG_DIFF_RANGE, $cyan)
            ->withFormat(Format::TAG_DIFF_ADDITION, $green)
            ->withFormat(Format::TAG_DIFF_REMOVAL, $red);
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
            ->set(Console::LEVELS_ERRORS, Console::TYPES_ALL, new MessageFormat($boldRed, $default, $boldRed))
            ->set(Console::LEVEL_WARNING, Console::TYPES_ALL, new MessageFormat($yellow, $default, $boldYellow))
            ->set(Console::LEVEL_NOTICE, Console::TYPES_ALL, new MessageFormat($bold, $cyan, $boldCyan))
            ->set(Console::LEVEL_INFO, Console::TYPES_ALL, new MessageFormat($default, $yellow, $yellow))
            ->set(Console::LEVEL_DEBUG, Console::TYPES_ALL, new MessageFormat($dim, $dim, $dim))
            ->set(Console::LEVELS_INFO, Console::TYPE_PROGRESS, new MessageFormat($default, $yellow, $yellow))
            ->set(Console::LEVELS_INFO, Console::TYPES_GROUP, new MessageFormat($boldMagenta, $default, $boldMagenta))
            ->set(Console::LEVELS_INFO, Console::TYPE_SUMMARY, new MessageFormat($default, $default, $bold))
            ->set(Console::LEVELS_INFO, Console::TYPE_SUCCESS, new MessageFormat($green, $default, $boldGreen))
            ->set(Console::LEVELS_ERRORS_AND_WARNINGS, Console::TYPE_FAILURE, new MessageFormat($yellow, $default, $boldYellow));
    }
}
