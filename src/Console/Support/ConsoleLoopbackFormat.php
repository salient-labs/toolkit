<?php declare(strict_types=1);

namespace Lkrms\Console\Support;

use Lkrms\Console\Contract\IConsoleFormat;

final class ConsoleLoopbackFormat implements IConsoleFormat
{
    /**
     * @var string
     */
    private $Before;

    /**
     * @var string
     */
    private $After;

    public function __construct(string $before = '', string $after = '')
    {
        $this->Before = $before;
        $this->After = $after;
    }

    public function apply(?string $text, ?string $tag = null, array $attributes = []): string
    {
        if (($text ?? '') === '') {
            return '';
        }

        $before = ($tag ?? '') === '' ? $this->Before : $tag;
        $after = ($tag ?? '') === '' ? $this->After : ($tag === '<' ? '>' : $tag);

        if ($before === '##') {
            $before = '## ';
            $after = ' ##';
        } elseif ($this->Before === '```') {
            $before .= ($attributes['infoString'] ?? '') . "\n";
            $after = "\n" . $after;
        }

        return $before . $text . $after;
    }
}
