<?php declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\Contract\IConsoleFormat;

/**
 * Applies formatting to console output by adding inline character sequences
 * defined by the target
 *
 */
final class ConsoleFormat implements IConsoleFormat
{
    /**
     * @var string
     */
    private $Before;

    /**
     * @var string
     */
    private $After;

    /**
     * @var string[]
     */
    private $Replace;

    /**
     * @var string[]
     */
    private $ReplaceWith;

    /**
     * @param array<string,string> $replace
     */
    public function __construct(string $before = '', string $after = '', array $replace = [])
    {
        $this->Before = $before;
        $this->After = $after;
        $this->Replace = array_keys($replace);
        $this->ReplaceWith = array_values($replace);
    }

    public function apply(?string $text, ?string $tag = null, array $attributes = []): string
    {
        if (($text ?? '') === '') {
            return '';
        }

        if ($this->Replace) {
            $text = str_replace($this->Replace, $this->ReplaceWith, $text);
        }

        $text = $this->Before . $text;

        if ($this->After === '') {
            return $text;
        }

        // Preserve trailing carriage returns
        if ($text[-1] === "\r") {
            return substr($text, 0, -1) . $this->After . "\r";
        }

        return $text . $this->After;
    }
}
