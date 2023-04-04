<?php declare(strict_types=1);

namespace Lkrms\Console;

/**
 * Character sequences that can be added before and after text to change its
 * appearance
 *
 */
final class ConsoleFormat
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

    public function apply(?string $text): string
    {
        if (!$text) {
            return '';
        }

        if ($this->Replace) {
            $text = str_replace($this->Replace, $this->ReplaceWith, $text);
        }

        $text = $this->Before . $text;

        // Preserve trailing carriage returns
        if ($text[-1] === "\r") {
            return substr($text, 0, -1) . $this->After . "\r";
        }

        return $text . $this->After;
    }
}
