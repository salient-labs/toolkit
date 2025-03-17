<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\FormatInterface;

/**
 * @internal
 *
 * @phpstan-require-implements FormatInterface
 */
trait EncloseAndReplaceTrait
{
    use EncloseTrait;

    /** @var string[] */
    private array $Search;
    /** @var string[] */
    private array $Replace;

    /**
     * @param array<string,string> $replace
     */
    private function __construct(string $before, string $after, array $replace)
    {
        $this->Before = $before;
        $this->After = $after;
        $this->Search = array_keys($replace);
        $this->Replace = array_values($replace);
    }

    /**
     * @param non-empty-string $string
     */
    private function encloseAndReplace(string $string): string
    {
        if ($this->Search) {
            $string = str_replace($this->Search, $this->Replace, $string);

            if ($string === '') {
                return '';
            }
        }

        return $this->enclose($string, $this->Before, $this->After);
    }
}
