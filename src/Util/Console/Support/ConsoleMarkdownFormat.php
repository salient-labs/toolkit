<?php declare(strict_types=1);

namespace Lkrms\Console\Support;

use Lkrms\Console\Catalog\ConsoleTag as Tag;
use Lkrms\Console\Contract\ConsoleFormatInterface;
use Lkrms\Console\Contract\ConsoleFormatterFactory;
use Lkrms\Console\Contract\ConsoleTagFormatFactory;
use Lkrms\Console\Support\ConsoleTagAttributes as TagAttributes;
use Lkrms\Console\Support\ConsoleTagFormats as TagFormats;
use Lkrms\Console\ConsoleFormatter as Formatter;

/**
 * Applies Markdown formatting to console output
 */
final class ConsoleMarkdownFormat implements
    ConsoleFormatInterface,
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
        if ((string) $text === '') {
            return '';
        }

        $before = $this->Before;
        $after = $this->After;

        $tag = $attributes instanceof TagAttributes
            ? $attributes->OpenTag
            : '';

        if ($tag === '##') {
            return '## ' . $text;
        }

        if (($tag === '_' || $tag === '*') && (
            !($attributes instanceof TagAttributes) ||
            !$attributes->HasChildren
        )) {
            return '`' . Formatter::unescapeTags($text) . '`';
        }

        if ($before === '`') {
            return '**`' . $text . '`**';
        }

        if ($before === '```') {
            return $attributes instanceof TagAttributes
                ? $tag . $attributes->InfoString . "\n"
                    . $text . "\n"
                    . $attributes->Indent . $tag
                : $tag . "\n"
                    . $text . "\n"
                    . $tag;
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
        return (new TagFormats(false, true))
            ->set(Tag::HEADING, new self('***', '***'))
            ->set(Tag::BOLD, new self('**', '**'))
            ->set(Tag::ITALIC, new self('*', '*'))
            ->set(Tag::UNDERLINE, new self('*<u>', '</u>*'))
            ->set(Tag::LOW_PRIORITY, new self('<small>', '</small>'))
            ->set(Tag::CODE_SPAN, new self('`', '`'))
            ->set(Tag::CODE_BLOCK, new self('```', '```'));
    }
}