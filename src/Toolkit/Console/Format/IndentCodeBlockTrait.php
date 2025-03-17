<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\FormatInterface;
use Salient\Contract\Console\Format\MessageAttributesInterface;
use Salient\Contract\Console\Format\TagAttributesInterface;

/**
 * @internal
 *
 * @phpstan-require-implements FormatInterface
 */
trait IndentCodeBlockTrait
{
    /**
     * @param non-empty-string $string
     * @param TagAttributesInterface|MessageAttributesInterface|null $attributes
     * @return non-empty-string
     */
    private function indentCodeBlock(string $string, $attributes): string
    {
        // With fenced code blocks:
        // - remove block indentation from the first line of code
        // - add a level of indentation to the block
        if (
            $attributes instanceof TagAttributesInterface
            && $attributes->getTag() === TagAttributesInterface::TAG_CODE_BLOCK
        ) {
            $indent = (string) $attributes->getIndent();
            if ($indent !== '') {
                $length = strlen($indent);
                if (substr($string, 0, $length) === $indent) {
                    $string = substr($string, $length);
                }
            }
            $string = '    ' . str_replace("\n", "\n    ", $string);
        }

        return $string;
    }
}
