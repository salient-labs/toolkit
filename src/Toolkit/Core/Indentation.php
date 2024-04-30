<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Core\Utility\File;
use Stringable;

/**
 * @api
 */
final class Indentation
{
    /**
     * @readonly
     */
    public bool $InsertSpaces;

    /**
     * @readonly
     */
    public int $TabSize;

    public function __construct(bool $insertSpaces, int $tabSize)
    {
        $this->InsertSpaces = $insertSpaces;
        $this->TabSize = $tabSize;
    }

    /**
     * Guess the indentation used in a file or stream
     *
     * Derived from VS Code's `indentationGuesser`.
     *
     * Returns `$default` if `$resource` appears to use the default indentation.
     *
     * @param Stringable|string|resource $resource
     * @param Stringable|string|null $uri
     *
     * @link https://github.com/microsoft/vscode/blob/860d67064a9c1ef8ce0c8de35a78bea01033f76c/src/vs/editor/common/model/indentationGuesser.ts
     */
    public static function from(
        $resource,
        ?self $default = null,
        bool $alwaysGuessTabSize = false,
        $uri = null
    ): self {
        $handle = File::maybeOpen($resource, 'r', $close, $uri);

        $lines = 0;
        $linesWithTabs = 0;
        $linesWithSpaces = 0;
        $diffSpacesCount = [2 => 0, 0, 0, 0, 0, 0, 0];

        $prevLine = '';
        $prevOffset = 0;
        while ($lines < 10000) {
            $line = @fgets($handle);
            if ($line === false) {
                File::checkEof($handle, $uri);
                break;
            }

            $lines++;

            $line = rtrim($line);
            if ($line === '') {
                continue;
            }

            $length = strlen($line);
            $spaces = 0;
            $tabs = 0;
            for ($offset = 0; $offset < $length; $offset++) {
                if ($line[$offset] === "\t") {
                    $tabs++;
                } elseif ($line[$offset] === ' ') {
                    $spaces++;
                } else {
                    break;
                }
            }

            if ($tabs) {
                $linesWithTabs++;
            } elseif ($spaces > 1) {
                $linesWithSpaces++;
            }

            $minOffset = $prevOffset < $offset ? $prevOffset : $offset;
            for ($i = 0; $i < $minOffset; $i++) {
                if ($prevLine[$i] !== $line[$i]) {
                    break;
                }
            }

            $prevLineSpaces = 0;
            $prevLineTabs = 0;
            for ($j = $i; $j < $prevOffset; $j++) {
                if ($prevLine[$j] === ' ') {
                    $prevLineSpaces++;
                } else {
                    $prevLineTabs++;
                }
            }

            $lineSpaces = 0;
            $lineTabs = 0;
            for ($j = $i; $j < $offset; $j++) {
                if ($line[$j] === ' ') {
                    $lineSpaces++;
                } else {
                    $lineTabs++;
                }
            }

            $_prevLine = $prevLine;
            $_prevOffset = $prevOffset;
            $_line = $line;

            $prevLine = $line;
            $prevOffset = $offset;

            if (
                ($prevLineSpaces && $prevLineTabs)
                || ($lineSpaces && $lineTabs)
            ) {
                continue;
            }

            $diffSpaces = abs($prevLineSpaces - $lineSpaces);
            $diffTabs = abs($prevLineTabs - $lineTabs);
            if (!$diffTabs) {
                // Skip if the difference could be alignment-related and doesn't
                // match the file's default indentation
                if (
                    $diffSpaces
                    && $lineSpaces
                    && $lineSpaces - 1 < strlen($_prevLine)
                    && $_line[$lineSpaces] !== ' '
                    && $_prevLine[$lineSpaces - 1] === ' '
                    && $_prevLine[-1] === ','
                    && !(
                        $default
                        && $default->InsertSpaces
                        && $default->TabSize === $diffSpaces
                    )
                ) {
                    $prevLine = $_prevLine;
                    $prevOffset = $_prevOffset;
                    continue;
                }
            } elseif ($diffSpaces % $diffTabs === 0) {
                $diffSpaces /= $diffTabs;
            } else {
                continue;
            }

            if ($diffSpaces > 1 && $diffSpaces <= 8) {
                $diffSpacesCount[$diffSpaces]++;
            }
        }

        $insertSpaces = $linesWithTabs === $linesWithSpaces
            ? $default->InsertSpaces ?? true
            : $linesWithTabs < $linesWithSpaces;

        $tabSize = $default->TabSize ?? 4;

        // Only guess tab size if inserting spaces
        if ($insertSpaces || $alwaysGuessTabSize) {
            $count = 0;
            foreach ([2, 4, 6, 8, 3, 5, 7] as $diffSpaces) {
                if ($diffSpacesCount[$diffSpaces] > $count) {
                    $tabSize = $diffSpaces;
                    $count = $diffSpacesCount[$diffSpaces];
                }
            }
        }

        if ($close) {
            File::close($handle, $uri);
        }

        if (
            $default
            && $default->InsertSpaces === $insertSpaces
            && $default->TabSize === $tabSize
        ) {
            return $default;
        }

        return new self($insertSpaces, $tabSize);
    }
}
