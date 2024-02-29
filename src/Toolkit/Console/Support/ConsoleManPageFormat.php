<?php declare(strict_types=1);

namespace Salient\Console\Support;

use Salient\Console\Contract\ConsoleFormatInterface;
use Salient\Console\Contract\ConsoleFormatterFactory;
use Salient\Console\Contract\ConsoleTagFormatFactory;
use Salient\Console\Support\ConsoleTagAttributes as TagAttributes;
use Salient\Console\Support\ConsoleTagFormats as TagFormats;
use Salient\Console\ConsoleFormatter as Formatter;
use Salient\Contract\Console\ConsoleTag as Tag;

/**
 * Applies Markdown formatting with man page extensions to console output
 */
final class ConsoleManPageFormat implements
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
            return '# ' . $text;
        }

        if ($tag === '_') {
            return $text;
        }

        if ($before === '`') {
            return '**`' . $text . '`**';
        }

        if ($before === '```') {
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
        return (new TagFormats(false, true))
            ->set(Tag::HEADING, new self('***', '***'))
            ->set(Tag::BOLD, new self('**', '**'))
            ->set(Tag::ITALIC, new self('*', '*'))
            ->set(Tag::UNDERLINE, new self('*', '*'))
            ->set(Tag::LOW_PRIORITY, new self('', ''))
            ->set(Tag::CODE_SPAN, new self('`', '`'))
            ->set(Tag::CODE_BLOCK, new self('```', '```'));
    }
}
