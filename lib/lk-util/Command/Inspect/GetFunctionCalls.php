<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Inspect;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Catalog\CliOptionValueType;
use Lkrms\Cli\CliOption;
use Lkrms\LkUtil\Command\Concept\Command;
use Lkrms\Support\TokenExtractor;

class GetFunctionCalls extends Command
{
    /**
     * @var string[]
     */
    private $Files;

    public function description(): string
    {
        return 'Print a list of functions called by PHP code';
    }

    protected function getOptionList(): array
    {
        return [
            CliOption::build()
                ->long('file')
                ->description('Files to inspect')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->valueType(CliOptionValueType::FILE)
                ->multipleAllowed()
                ->required()
                ->bindTo($this->Files),
        ];
    }

    protected function run(string ...$args)
    {
        foreach ($this->Files as $file) {
            $tokens = new TokenExtractor($file);
        }
    }
}
