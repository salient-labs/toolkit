<?php

declare(strict_types=1);

namespace Lkrms\Tests\Cli\Command;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Concept\CliCommand;

class TestOptions extends CliCommand
{
    public function getDescription(): string
    {
        return "Test the available option types";
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
            (CliOption::build()
                ->long("source")
                ->description("One or more sources")
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->multipleAllowed()
                ->go()),
            (CliOption::build()
                ->long("target")
                ->valueName("targetDir")
                ->description("Target directory")
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->go()),
            (CliOption::build()
                ->long("verbose")
                ->short("v")
                ->description("Increase verbosity")
                ->multipleAllowed()
                ->go()),
            (CliOption::build()
                ->long("archive")
                ->short("a")
                ->description("Archive mode")
                ->go()),
            (CliOption::build()
                ->long("stderr")
                ->description("Change stderr output mode")
                ->valueName("MODE")
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(["errors", "all"])
                ->defaultValue("errors")
                ->go()),
            (CliOption::build()
                ->long("backup-dir")
                ->description("Make backups into hierarchy based in DIR")
                ->valueName("DIR")
                ->optionType(CliOptionType::VALUE)
                ->go()),
            (CliOption::build()
                ->long("links")
                ->short("l")
                ->description("Copy symlinks as symlinks")
                ->go()),
            (CliOption::build()
                ->long("copy-links")
                ->short("L")
                ->description("Transform symlink into referent file/dir")
                ->go()),
            (CliOption::build()
                ->long("in-place")
                ->short("i")
                ->valueName("SUFFIX")
                ->description("Edit files in place")
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->go()),
            (CliOption::build()
                ->long("refresh")
                ->short("r")
                ->valueName("CACHE")
                ->description("Ignore locally cached data")
                ->optionType(CliOptionType::ONE_OF_OPTIONAL)
                ->allowedValues(["all", "hosts", "users"])
                ->multipleAllowed()
                ->defaultValue(["all"])
                ->go()),
        ];
    }

    protected function run(string ...$args)
    {
        var_dump($this->getOptionValues());
        var_dump($args);
    }
}
