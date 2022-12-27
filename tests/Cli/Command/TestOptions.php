<?php declare(strict_types=1);

namespace Lkrms\Tests\Cli\Command;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Concept\CliCommand;

class TestOptions extends CliCommand
{
    private $Operation;

    public function getDescription(): string
    {
        return 'Test the available option types';
    }

    protected function getOptionList(): array
    {
        // Defaults:
        //
        //     "long"            => "",
        //     "short"           => "",
        //     "valueName"       => "VALUE",
        //     "description"     => null,
        //     "optionType"      => CliOptionType::FLAG,
        //     "allowedValues"   => null,
        //     "required"        => false,
        //     "multipleAllowed" => false,
        //     "defaultValue"    => null,
        return [
            CliOption::build()
                ->long('operation')
                ->valueName('jobType')
                ->description('Task to complete')
                ->optionType(CliOptionType::ONE_OF_POSITIONAL)
                ->allowedValues(['compress', 'copy'])
                ->bindTo($this->Operation)
                ->required(),
            CliOption::build()
                ->long('source')
                ->description('One or more sources')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->multipleAllowed()
                ->required()
                ->go(),
            CliOption::build()
                ->long('target')
                ->valueName('targetDir')
                ->description('Target directory')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required(),
            CliOption::build()
                ->long('verbose')
                ->short('v')
                ->description('Increase verbosity')
                ->multipleAllowed(),
            CliOption::build()
                ->long('archive')
                ->short('a')
                ->description('Archive mode'),
            CliOption::build()
                ->long('stderr')
                ->description('Change stderr output mode')
                ->valueName('MODE')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(['errors', 'all'])
                ->defaultValue('errors'),
            CliOption::build()
                ->long('backup-dir')
                ->description('Make backups into hierarchy based in DIR')
                ->valueName('DIR')
                ->optionType(CliOptionType::VALUE),
            CliOption::build()
                ->long('links')
                ->short('l')
                ->description('Copy symlinks as symlinks'),
            CliOption::build()
                ->long('copy-links')
                ->short('L')
                ->description('Transform symlink into referent file/dir'),
            CliOption::build()
                ->long('in-place')
                ->short('i')
                ->valueName('SUFFIX')
                ->description('Edit files in place')
                ->optionType(CliOptionType::VALUE_OPTIONAL),
            CliOption::build()
                ->long('refresh')
                ->short('r')
                ->valueName('CACHE')
                ->description('Ignore locally cached data')
                ->optionType(CliOptionType::ONE_OF_OPTIONAL)
                ->allowedValues(['all', 'hosts', 'users'])
                ->multipleAllowed()
                ->defaultValue(['all']),
        ];
    }

    protected function run(string ...$args)
    {
        var_dump($this->getOptionValues());
        var_dump($args);
        var_dump(['$this->Operation' => $this->Operation]);
    }
}
