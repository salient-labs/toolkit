<?php

declare(strict_types=1);

namespace Lkrms\Tests\Cli;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use UnexpectedValueException;

final class CliOptionBuilderTest extends \Lkrms\Tests\TestCase
{
    public function testBuild()
    {
        $option = (CliOption::build()
            ->long("dest")
            ->short("d")
            ->valueName("DIR")
            ->description("Sync files to DIR")
            ->optionType(CliOptionType::VALUE)
            ->required(true)
            ->go());
        $this->assertEquals('dest', $option->Long);
        $this->assertEquals('d', $option->Short);
        $this->assertEquals('d|dest', $option->Key);
        $this->assertEquals('--dest', $option->DisplayName);
        $this->assertEquals(CliOptionType::VALUE, $option->OptionType);
        $this->assertEquals(false, $option->IsFlag);
        $this->assertEquals(true, $option->IsRequired);
        $this->assertEquals(true, $option->IsValueRequired);
        $this->assertEquals(false, $option->MultipleAllowed);
        $this->assertEquals(NULL, $option->Delimiter);
        $this->assertEquals('DIR', $option->ValueName);
        $this->assertEquals('Sync files to DIR', $option->Description);
        $this->assertEquals(NULL, $option->AllowedValues);
        $this->assertEquals(NULL, $option->DefaultValue);
        $this->assertEquals(NULL, $option->EnvironmentVariable);
    }

    public function testInvalidBuild()
    {
        $this->expectException(UnexpectedValueException::class);
        (CliOption::build()
            ->long("dest")
            ->short("d")
            ->value("DIR")
            ->desc("Sync files to DIR")
            ->type(CliOptionType::VALUE)
            ->required(true)
            ->go());
    }

}
