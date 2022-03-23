<?php

declare(strict_types=1);

namespace Lkrms\Tests\Cli\Command;

use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;

class TestOptions extends CliCommand
{
    protected function getDefaultName(): array
    {
        return [
            "test",
            "options"
        ];
    }

    protected function getOptionList(): array
    {
        //[
        //    "long"            => "",
        //    "short"           => "",
        //    "valueName"       => "VALUE",
        //    "description"     => null,
        //    "optionType"      => CliOptionType::FLAG,
        //    "allowedValues"   => null,
        //    "required"        => false,
        //    "multipleAllowed" => false,
        //    "defaultValue"    => null,
        //],
        return [
            [
                "long"            => "verbose",
                "short"           => "v",
                "description"     => "Increase verbosity",
                "multipleAllowed" => true,
            ],
            [
                "long"        => "archive",
                "short"       => "a",
                "description" => "Archive mode",
            ],
            [
                "long"          => "stderr",
                "description"   => "Change stderr output mode",
                "valueName"     => "MODE",
                "optionType"    => CliOptionType::ONE_OF,
                "allowedValues" => [
                    "errors",
                    "all"
                ],
                "defaultValue" => "errors",
            ],
            [
                "long"        => "backup-dir",
                "description" => "Make backups into hierarchy based in DIR",
                "valueName"   => "DIR",
                "optionType"  => CliOptionType::VALUE,
            ],
            [
                "long"        => "links",
                "short"       => "l",
                "description" => "Copy symlinks as symlinks",
            ],
            [
                "long"        => "copy-links",
                "short"       => "L",
                "description" => "Transform symlink into referent file/dir",
            ],
            [
                "long"        => "in-place",
                "short"       => "i",
                "valueName"   => "SUFFIX",
                "description" => "Edit files in place",
                "optionType"  => CliOptionType::VALUE_OPTIONAL,
            ],
        ];
    }

    protected function run(...$args)
    {
        var_dump($this->getAllOptionValues());
        var_dump($args);
    }
}

