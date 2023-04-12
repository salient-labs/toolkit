<?php declare(strict_types=1);

namespace Lkrms\Tests\Cli;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionBuilder;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Enumeration\CliOptionUnknownValuePolicy;
use Lkrms\Cli\Enumeration\CliOptionValueType;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;

final class CliOptionBuilderTest extends \Lkrms\Tests\TestCase
{
    public function testFlag()
    {
        $option = $this->getFlag()->go()
                                  ->validate();
        $this->assertIsFlag($option);
        $this->assertSame(CliOptionValueType::BOOLEAN, $option->ValueType);
        $this->assertSame(false, $option->MultipleAllowed);
        $this->assertSame(false, $option->DefaultValue);
        $this->assertSame(null, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);

        $option = $this->getFlag()->multipleAllowed()
                                  ->go()
                                  ->validate();
        $this->assertIsFlag($option);
        $this->assertSame(CliOptionValueType::INTEGER, $option->ValueType);
        $this->assertSame(true, $option->MultipleAllowed);
        $this->assertSame(0, $option->DefaultValue);
        $this->assertSame(null, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);

        $_ENV[__METHOD__] = '1';

        $option = $this->getFlag()->envVariable(__METHOD__)
                                  ->go()
                                  ->validate();
        $this->assertSame(false, $option->MultipleAllowed);
        $this->assertSame(true, $option->DefaultValue);
        $this->assertSame(__METHOD__, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);

        $option = $this->getFlag()->envVariable(__METHOD__)
                                  ->multipleAllowed()
                                  ->go()
                                  ->validate();
        $this->assertSame(true, $option->MultipleAllowed);
        $this->assertSame(1, $option->DefaultValue);
        $this->assertSame(__METHOD__, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);

        unset($_ENV[__METHOD__]);

        $option = $this->getFlag()->envVariable(__METHOD__)
                                  ->go()
                                  ->validate();
        $this->assertSame(false, $option->MultipleAllowed);
        $this->assertSame(false, $option->DefaultValue);
        $this->assertSame(__METHOD__, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);
    }

    public function testValue()
    {
        $option = CliOption::build(new Container())
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
        $this->assertSame('DIR', $option->ValueName);
        $this->assertSame('--dest', $option->DisplayName);
        $this->assertSame(CliOptionType::VALUE, $option->OptionType);
        $this->assertSame(CliOptionValueType::STRING, $option->ValueType);
        $this->assertSame(false, $option->IsFlag);
        $this->assertSame(false, $option->IsOneOf);
        $this->assertSame(false, $option->IsPositional);
        $this->assertSame(true, $option->Required);
        $this->assertSame(true, $option->ValueRequired);
        $this->assertSame(false, $option->MultipleAllowed);
        $this->assertSame(null, $option->Delimiter);
        $this->assertSame('Sync files to DIR', $option->Description);
        $this->assertSame(null, $option->AllowedValues);
        $this->assertSame(null, $option->UnknownValuePolicy);
        $this->assertSame(false, $option->AddAll);
        $this->assertSame(null, $option->DefaultValue);
        $this->assertSame(false, $option->KeepDefault);
        $this->assertSame(null, $option->EnvVariable);
        $this->assertSame(false, $option->KeepEnv);
        $this->assertSame(null, $option->ValueCallback);
        $this->assertSame(false, $option->Hide);
    }

    private function assertIsFlag(CliOption $option)
    {
        $this->assertSame('flag', $option->Long);
        $this->assertSame('f', $option->Short);
        $this->assertSame('f|flag', $option->Key);
        $this->assertSame(null, $option->ValueName);
        $this->assertSame('--flag', $option->DisplayName);
        $this->assertSame(CliOptionType::FLAG, $option->OptionType);
        $this->assertSame(true, $option->IsFlag);
        $this->assertSame(false, $option->IsOneOf);
        $this->assertSame(false, $option->IsPositional);
        $this->assertSame(false, $option->Required);
        $this->assertSame(false, $option->ValueRequired);
        $this->assertSame(null, $option->Delimiter);
        $this->assertSame('Description of flag', $option->Description);
        $this->assertSame(null, $option->AllowedValues);
        $this->assertSame(null, $option->UnknownValuePolicy);
        $this->assertSame(false, $option->AddAll);
        $this->assertSame(false, $option->KeepDefault);
        $this->assertSame(false, $option->KeepEnv);
        $this->assertSame(false, $option->Hide);
    }

    private function getFlag(?CliOptionBuilder $option = null): CliOptionBuilder
    {
        return
            ($option ?: $this->getOption())
                ->long('flag')
                ->short('f')
                ->description('Description of flag')
                ->optionType(CliOptionType::FLAG);
    }

    private function getOption(): CliOptionBuilder
    {
        return
            CliOption::build($this->getContainer())
                ->valueName('Start Date')
                ->valueType(CliOptionValueType::DATE)
                ->allowedValues(['today', 'yesterday', 'tomorrow'])
                ->unknownValuePolicy(CliOptionUnknownValuePolicy::ACCEPT)
                ->required()
                ->addAll()
                ->defaultValue(['today'])
                ->keepDefault()
                ->keepEnv()
                ->delimiter(':');
    }

    private function getContainer(): IContainer
    {
        return new Container();
    }
}
