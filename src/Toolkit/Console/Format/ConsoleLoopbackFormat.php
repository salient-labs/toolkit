<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Console\Format\ConsoleFormatter as Formatter;
use Salient\Console\Format\ConsoleTagAttributes as TagAttributes;
use Salient\Console\Format\ConsoleTagFormats as TagFormats;
use Salient\Contract\Console\Format\ConsoleTag as Tag;
use Salient\Contract\Console\Format\FormatInterface;

/**
 * Reapplies the output's original inline formatting tags
 */
final class ConsoleLoopbackFormat implements
    FormatInterface,
    ConsoleFormatterFactory,
    ConsoleTagFormatFactory
{
    private string $Before;
    private string $After;

    public function __construct(string $before = '', string $after = '')
    {
        $this->Before = $before;
        $this->After = $after;
    }

    /**
     * @inheritDoc
     */
    public function apply(?string $text, $attributes = null): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $before = $this->Before;
        $after = $this->After;

        if (
            $attributes instanceof TagAttributes
            && $attributes->OpenTag !== ''
        ) {
            $before = $attributes->OpenTag;
            $after = $before === '<' ? '>' : $attributes->OpenTag;
        }

        if ($before === '##') {
            return '## ' . $text . ' ##';
        }

        if ($this->Before === '```') {
            return $attributes instanceof TagAttributes
                ? $before . $attributes->InfoString . "\n"
                    . $text . "\n"
                    . $attributes->Indent . $after
                : $before . "\n"
                    . $text . "\n"
                    . $after;
        }

        return $before . $text . $after;
    }

    /**
     * @inheritDoc
     */
    public static function getFormatter(): Formatter
    {
        return new Formatter(self::getTagFormats());
    }

    /**
     * @inheritDoc
     */
    public static function getTagFormats(): TagFormats
    {
        return (new TagFormats(false))
            ->withFormat(Tag::HEADING, new self('***', '***'))
            ->withFormat(Tag::BOLD, new self('**', '**'))
            ->withFormat(Tag::ITALIC, new self('*', '*'))
            ->withFormat(Tag::UNDERLINE, new self('<', '>'))
            ->withFormat(Tag::LOW_PRIORITY, new self('~~', '~~'))
            ->withFormat(Tag::CODE_SPAN, new self('`', '`'))
            ->withFormat(Tag::CODE_BLOCK, new self('```', '```'));
    }
}
