<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\FormatInterface;
use Salient\Contract\Console\Format\MessageAttributesInterface;
use Salient\Contract\Console\Format\TagAttributesInterface;

/**
 * @api
 *
 * @phpstan-require-implements FormatInterface
 */
trait EncloseAndReplaceFormatTrait
{
    private string $Before;
    private string $After;
    /** @var string[] */
    private array $Search;
    /** @var string[] */
    private array $Replace;

    /**
     * @api
     *
     * @param array<string,string> $replace
     */
    public function __construct(string $before = '', string $after = '', array $replace = [])
    {
        $this->Before = $before;
        $this->After = $after;
        $this->Search = array_keys($replace);
        $this->Replace = array_values($replace);
    }

    /**
     * Format a string
     *
     * @param TagAttributesInterface|MessageAttributesInterface|null $attributes
     */
    public function apply(string $string, $attributes = null): string
    {
        if ($string === '') {
            return '';
        }

        if ($this->Search) {
            $string = str_replace($this->Search, $this->Replace, $string);
        }

        $string = $this->Before . $string;

        if ($this->After === '') {
            return $string;
        }

        // Keep trailing carriage returns at the end of the string
        if ($string[-1] === "\r") {
            return substr($string, 0, -1) . $this->After . "\r";
        }

        return $string . $this->After;
    }
}
