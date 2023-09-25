<?php declare(strict_types=1);

namespace Lkrms\Console\Support;

use Lkrms\Console\Catalog\ConsoleAttribute as Attribute;
use Lkrms\Console\Contract\IConsoleFormat;
use Lkrms\Console\ConsoleFormatter;

/**
 * Applies Markdown formatting to console output
 */
final class ConsoleMarkdownFormat implements IConsoleFormat
{
    private string $Before;

    private string $After;

    public function __construct(string $before = '', string $after = '')
    {
        $this->Before = $before;
        $this->After = $after;
    }

    public function apply(?string $text, array $attributes = []): string
    {
        if (($text ?? '') === '') {
            return '';
        }

        $tag = $attributes[Attribute::TAG] ?? '';

        $before = $this->Before;
        $after = $this->After;

        if ($tag === '##') {
            $before = '## ';
            $after = '';
        } elseif ($tag === '_' || $tag === '*') {
            $before = '`';
            $after = '`';
            $text = ConsoleFormatter::removeTags($text);
        } elseif ($this->Before === '`') {
            $before = '**`';
            $after = '`**';
        } elseif ($this->Before === '```') {
            $before = $tag . ($attributes[Attribute::INFO_STRING] ?? '') . "\n";
            $after = "\n" . $tag;
        }

        return $before . $text . $after;
    }
}
