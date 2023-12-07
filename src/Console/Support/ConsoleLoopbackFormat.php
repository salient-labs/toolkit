<?php declare(strict_types=1);

namespace Lkrms\Console\Support;

use Lkrms\Console\Catalog\ConsoleAttribute as Attribute;
use Lkrms\Console\Contract\IConsoleFormat;

/**
 * Reapplies the output's original Markdown-like inline formatting tags
 */
final class ConsoleLoopbackFormat implements IConsoleFormat
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
        if ((string) $text === '') {
            return '';
        }

        $tag = $attributes[Attribute::TAG] ?? '';
        $before = $tag === '' ? $this->Before : $tag;
        $after = $tag === '' ? $this->After : ($tag === '<' ? '>' : $tag);

        if ($before === '##') {
            $before = '## ';
            $after = ' ##';
        } elseif ($this->Before === '```') {
            $before .= ($attributes[Attribute::INFO_STRING] ?? '') . "\n";
            $after = "\n" . ($attributes[Attribute::INDENT] ?? '') . $after;
        }

        return $before . $text . $after;
    }
}
