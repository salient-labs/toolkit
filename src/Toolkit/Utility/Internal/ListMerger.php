<?php declare(strict_types=1);

namespace Salient\Utility\Internal;

use Salient\Utility\Regex;
use Salient\Utility\Str;

/**
 * @internal
 */
final class ListMerger
{
    private string $ListSeparator;
    private ?string $HeadingPrefix;
    private string $ItemRegex;
    private bool $Clean;
    private bool $Loose;
    private bool $DiscardEmpty;
    private string $Eol;
    /** @var int<1,max> */
    private int $TabSize;
    private bool $PrefixIsItem;
    private int $PrefixBytes;
    private string $Indent = '';

    /**
     * @param int<1,max> $tabSize
     */
    public function __construct(
        string $listSeparator,
        ?string $headingPrefix,
        string $itemRegex,
        bool $clean,
        bool $loose,
        bool $discardEmpty,
        string $eol,
        int $tabSize
    ) {
        $this->ListSeparator = $listSeparator;
        $this->HeadingPrefix = $headingPrefix;
        $this->ItemRegex = $itemRegex;
        $this->Clean = $clean;
        $this->Loose = $loose;
        $this->DiscardEmpty = $discardEmpty;
        $this->Eol = $eol;
        $this->TabSize = $tabSize;

        if ($this->HeadingPrefix !== null) {
            $this->PrefixIsItem = (bool) Regex::match($this->ItemRegex, $this->HeadingPrefix);
            $this->PrefixBytes = strlen($this->HeadingPrefix);
            $this->Indent = str_repeat(' ', mb_strlen($this->HeadingPrefix));
        }
    }

    public function merge(string $string): string
    {
        $lines = Regex::split('/\r\n|\n|\r/', $string);
        $count = count($lines);
        $lists = [];
        $lastWasItem = false;
        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            // Remove prefixes to ensure lists with the same heading are merged
            if (
                $this->HeadingPrefix !== null
                && !$this->PrefixIsItem
                && substr($line, 0, $this->PrefixBytes) === $this->HeadingPrefix
            ) {
                /** @var string */
                $line = substr($line, $this->PrefixBytes);
            }

            // Clear the current heading if this is an empty line after an item
            if (trim($line) === '') {
                if (!$this->Loose && $lastWasItem) {
                    unset($list);
                }
                continue;
            }

            if (Regex::match($this->ItemRegex, $line, $matches, \PREG_OFFSET_CAPTURE)) {
                // Collect subsequent lines with indentation of the same width
                if (
                    ($matches['indent'][1] ?? null) === 0
                    && ($itemIndent = $matches['indent'][0]) !== ''
                ) {
                    $itemIndent = Str::expandTabs($itemIndent, $this->TabSize);
                    $itemIndentLength = mb_strlen($itemIndent);
                    $itemIndent = str_repeat(' ', $itemIndentLength);
                    $tentative = '';
                    $backtrack = 0;
                    while ($i < $count - 1) {
                        $nextLine = $lines[$i + 1];
                        if (trim($nextLine) === '') {
                            $tentative .= $nextLine . $this->Eol;
                            $backtrack++;
                        } elseif (substr(Str::expandTabs($nextLine, $this->TabSize), 0, $itemIndentLength) === $itemIndent) {
                            $line .= $this->Eol . $tentative . $nextLine;
                            $tentative = '';
                            $backtrack = 0;
                        } else {
                            $i -= $backtrack;
                            break;
                        }
                        $i++;
                    }
                }
            } else {
                $list = $line;
            }

            $key = $list ?? $line;
            $lists[$key] ??= [];
            $lastWasItem = $key !== $line;
            if ($lastWasItem && !in_array($line, $lists[$key], true)) {
                $lists[$key][] = $line;
            }
        }

        // Move top-level lines to the top
        $top = [];
        $itemList = null;
        foreach ($lists as $list => $lines) {
            if (count($lines)) {
                continue;
            }

            unset($lists[$list]);

            if ($this->DiscardEmpty && !Regex::match($this->ItemRegex, $list)) {
                continue;
            }

            if ($this->Clean) {
                $top[$list] = [];
                continue;
            }

            // Move consecutive top-level items to their own list so
            // `$this->ListSeparator` isn't inserted between them
            if (Regex::match($this->ItemRegex, $list)) {
                if ($itemList !== null) {
                    $top[$itemList][] = $list;
                    continue;
                }
                $itemList = $list;
            } else {
                $itemList = null;
            }
            $top[$list] = [];
        }
        $lists = $top + $lists;

        $merged = [];
        foreach ($lists as $list => $lines) {
            if ($this->Clean) {
                $list = Regex::replace($this->ItemRegex, '', $list, 1);
            }

            if (
                $this->HeadingPrefix !== null
                && !($this->PrefixIsItem && substr($list, 0, $this->PrefixBytes) === $this->HeadingPrefix)
                && !Regex::match($this->ItemRegex, $list)
            ) {
                $list = $this->HeadingPrefix . $list;
                $listHasPrefix = true;
            } else {
                $listHasPrefix = false;
            }

            if (!$lines) {
                $merged[] = $list;
                continue;
            }

            // Don't separate or indent consecutive top-level items
            if (!$listHasPrefix && Regex::match($this->ItemRegex, $list)) {
                $merged[] = implode($this->Eol, [$list, ...$lines]);
                continue;
            }

            $merged[] = $list;
            $merged[] = $this->Indent . implode($this->Eol . $this->Indent, $lines);
        }

        return implode($this->ListSeparator, $merged);
    }
}
