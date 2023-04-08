<?php declare(strict_types=1);

namespace Lkrms\Tests\Cli;

use Lkrms\Cli\CliOptionBuilder;
use Lkrms\Cli\CliOptionType;
use Lkrms\Container\Container;
use UnexpectedValueException;

final class CliOptionBuilderTest extends \Lkrms\Tests\TestCase
{
    public function testFlag()
    {
        $option = CliOptionBuilder::build(new Container())
            ->long('verbose')
            ->short('v')
            ->description('Increase verbosity')
            ->multipleAllowed()
            ->go()
            ->validate();
        $this->assertSame('verbose', $option->Long);
        $this->assertSame('v', $option->Short);
        $this->assertSame('v|verbose', $option->Key);
        $this->assertSame('--verbose', $option->DisplayName);
        $this->assertSame(CliOptionType::FLAG, $option->OptionType);
        $this->assertSame(true, $option->IsFlag);
        $this->assertSame(false, $option->IsPositional);
        $this->assertSame(false, $option->Required);
        $this->assertSame(false, $option->ValueRequired);
        $this->assertSame(true, $option->MultipleAllowed);
        $this->assertSame(null, $option->Delimiter);
        $this->assertSame(null, $option->ValueName);
        $this->assertSame('Increase verbosity', $option->Description);
        $this->assertSame(null, $option->AllowedValues);
        $this->assertSame(null, $option->UnknownValuePolicy);
        $this->assertSame(false, $option->AddAll);
        $this->assertSame(0, $option->DefaultValue);
        $this->assertSame(false, $option->KeepDefault);
        $this->assertSame(null, $option->EnvVariable);
        $this->assertSame(false, $option->KeepEnv);

        $option = CliOptionBuilder::build(new Container())
            ->long('recursive')
            ->short('r')
            ->description('Recurse into directories')
            ->go()
            ->validate();
        $this->assertSame('recursive', $option->Long);
        $this->assertSame('r', $option->Short);
        $this->assertSame('r|recursive', $option->Key);
        $this->assertSame('--recursive', $option->DisplayName);
        $this->assertSame(CliOptionType::FLAG, $option->OptionType);
        $this->assertSame(true, $option->IsFlag);
        $this->assertSame(false, $option->IsPositional);
        $this->assertSame(false, $option->Required);
        $this->assertSame(false, $option->ValueRequired);
        $this->assertSame(false, $option->MultipleAllowed);
        $this->assertSame(null, $option->Delimiter);
        $this->assertSame(null, $option->ValueName);
        $this->assertSame('Recurse into directories', $option->Description);
        $this->assertSame(null, $option->AllowedValues);
        $this->assertSame(null, $option->UnknownValuePolicy);
        $this->assertSame(false, $option->AddAll);
        $this->assertSame(false, $option->DefaultValue);
        $this->assertSame(false, $option->KeepDefault);
        $this->assertSame(null, $option->EnvVariable);
        $this->assertSame(false, $option->KeepEnv);
    }

    public function testValue()
    {
        $option = CliOptionBuilder::build(new Container())
            ->long('dest')
            ->short('d')
            ->valueName('DIR')
            ->description('Sync files to DIR')
            ->optionType(CliOptionType::VALUE)
            ->required()
            ->go()
            ->validate();
        $this->assertSame('dest', $option->Long);
        $this->assertSame('d', $option->Short);
        $this->assertSame('d|dest', $option->Key);
        $this->assertSame('--dest', $option->DisplayName);
        $this->assertSame(CliOptionType::VALUE, $option->OptionType);
        $this->assertSame(false, $option->IsFlag);
        $this->assertSame(false, $option->IsPositional);
        $this->assertSame(true, $option->Required);
        $this->assertSame(true, $option->ValueRequired);
        $this->assertSame(false, $option->MultipleAllowed);
        $this->assertSame(null, $option->Delimiter);
        $this->assertSame('DIR', $option->ValueName);
        $this->assertSame('Sync files to DIR', $option->Description);
        $this->assertSame(null, $option->AllowedValues);
        $this->assertSame(null, $option->UnknownValuePolicy);
        $this->assertSame(false, $option->AddAll);
        $this->assertSame(null, $option->DefaultValue);
        $this->assertSame(false, $option->KeepDefault);
        $this->assertSame(null, $option->EnvVariable);
        $this->assertSame(false, $option->KeepEnv);
    }

    public function testInvalid()
    {
        $this->expectException(UnexpectedValueException::class);
        CliOptionBuilder::build(new Container())
            ->long('dest')
            ->short('d')
            ->value('DIR')  // "valueName"
            ->desc('Sync files to DIR')  // "description"
            ->type(CliOptionType::VALUE)  // "optionType"
            ->required(true)
            ->go();
    }
}
