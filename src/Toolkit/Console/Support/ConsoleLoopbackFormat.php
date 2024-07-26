<?php declare(strict_types=1);

namespace Salient\Console\Support;

use Salient\Console\Contract\ConsoleFormatterFactory;
use Salient\Console\Contract\ConsoleTagFormatFactory;
use Salient\Console\Support\ConsoleTagAttributes as TagAttributes;
use Salient\Console\Support\ConsoleTagFormats as TagFormats;
use Salient\Console\ConsoleFormatter as Formatter;
use Salient\Contract\Console\ConsoleFormatInterface;
use Salient\Contract\Console\ConsoleTag as Tag;

/**
 * Reapplies the output's original inline formatting tags
 */
final class ConsoleLoopbackFormat implements
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
        if ($text === null || $text === '') {
            return '';
        }

        $before = $this->Before;
        $after = $this->After;

        if (
            $attributes instanceof TagAttributes
            && $attributes->OpenTag !== null
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
            ->set(Tag::HEADING, new self('***', '***'))
            ->set(Tag::BOLD, new self('**', '**'))
            ->set(Tag::ITALIC, new self('*', '*'))
            ->set(Tag::UNDERLINE, new self('<', '>'))
            ->set(Tag::LOW_PRIORITY, new self('~~', '~~'))
            ->set(Tag::CODE_SPAN, new self('`', '`'))
            ->set(Tag::CODE_BLOCK, new self('```', '```'));
    }
}
