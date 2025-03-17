<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\FormatInterface;

/**
 * @internal
 *
 * @phpstan-require-implements FormatInterface
 */
trait EncloseTrait
{
    private string $Before;
    private string $After;

    private function __construct(string $before, string $after)
    {
        $this->Before = $before;
        $this->After = $after;
    }

    /**
     * @param non-empty-string $string
     */
    private function enclose(string $string, string $before, string $after): string
    {
        $string = $before . $string;

        if ($after === '') {
            return $string;
        }

        // Keep trailing carriage returns at the end of the string
        if ($string[-1] === "\r") {
            return substr($string, 0, -1) . $after . "\r";
        }

        return $string . $after;
    }
}
