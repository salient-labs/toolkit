<?php declare(strict_types=1);

namespace Salient\Sli\Command\Analyse;

use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Cli\CliOptionValueType;
use Salient\Sli\Command\AbstractCommand;
use Salient\Sli\Internal\TokenExtractor;

class AnalyseMembers extends AbstractCommand
{
    /** @var string[] */
    private array $Files = [];

    public function getDescription(): string
    {
        return 'Print a list of functions called by PHP code';
    }

    protected function getOptionList(): iterable
    {
        return [
            CliOption::build()
                ->name('path')
                ->description('Paths to analyse')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->valueType(CliOptionValueType::PATH)
                ->multipleAllowed()
                ->required()
                ->unique()
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
