<?php declare(strict_types=1);

namespace Lkrms\Tests\Cli;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Catalog\CliOptionValueType;
use Lkrms\Cli\Catalog\CliOptionValueUnknownPolicy;
use Lkrms\Cli\Catalog\CliOptionVisibility;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionBuilder;
use Lkrms\Concept\Builder;
use Lkrms\Container\Container;
use Lkrms\Container\ContainerInterface;
use Lkrms\Tests\TestCase;
use ReflectionClass;

final class CliOptionBuilderTest extends TestCase
{
    public function testFlag(): void
    {
        $option = $this
            ->getFlag()
            ->load();
        $this->assertIsFlag($option);
        $this->assertSame(CliOptionValueType::BOOLEAN, $option->ValueType);
        $this->assertSame(false, $option->MultipleAllowed);
        $this->assertSame(false, $option->Unique);
        $this->assertSame(false, $option->DefaultValue);
        $this->assertSame(false, $option->OriginalDefaultValue);
        $this->assertSame(null, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);
        $this->assertSame(false, $option->IsBound);
        $this->assertSame([
            'description' => 'Description of flag.',
            'type' => 'boolean',
            'default' => false,
        ], $option->getJsonSchema());

        $option = $this
            ->getFlag()
            ->multipleAllowed()
            ->load();
        $this->assertIsFlag($option);
        $this->assertSame(CliOptionValueType::INTEGER, $option->ValueType);
        $this->assertSame(true, $option->MultipleAllowed);
        $this->assertSame(true, $option->Unique);
        $this->assertSame(0, $option->DefaultValue);
        $this->assertSame(0, $option->OriginalDefaultValue);
        $this->assertSame(null, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);
        $this->assertSame(false, $option->IsBound);
        $this->assertSame([
            'description' => 'Description of flag.',
            'type' => 'integer',
            'default' => 0,
        ], $option->getJsonSchema());

        $_ENV[__METHOD__] = '1';

        $option = $this
            ->getFlag()
            ->envVariable(__METHOD__)
            ->load();
        $this->assertSame(false, $option->MultipleAllowed);
        $this->assertSame(true, $option->DefaultValue);
        $this->assertSame(false, $option->OriginalDefaultValue);
        $this->assertSame(__METHOD__, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);

        $option = $this
            ->getFlag()
            ->envVariable(__METHOD__)
            ->multipleAllowed()
            ->load();
        $this->assertSame(true, $option->MultipleAllowed);
        $this->assertSame(1, $option->DefaultValue);
        $this->assertSame(0, $option->OriginalDefaultValue);
        $this->assertSame(__METHOD__, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);

        unset($_ENV[__METHOD__]);

        $option = $this
            ->getFlag()
            ->envVariable(__METHOD__)
            ->load();
        $this->assertSame(false, $option->MultipleAllowed);
        $this->assertSame(false, $option->DefaultValue);
        $this->assertSame(false, $option->OriginalDefaultValue);
        $this->assertSame(__METHOD__, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);
    }

    private function assertIsFlag(CliOption $option): void
    {
        // No assertions for:
        // - ValueType
        // - MultipleAllowed
        // - Unique
        // - DefaultValue
        // - OriginalDefaultValue
        // - EnvVariable
        // - ValueCallback
        // - IsBound
        $this->assertSame('flag', $option->Name);
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
        $this->assertSame(false, $option->WasRequired);
        $this->assertSame(false, $option->ValueRequired);
        $this->assertSame(false, $option->ValueOptional);
        $this->assertSame(null, $option->Delimiter);
        $this->assertSame('Description of flag', $option->Description);
        $this->assertSame(null, $option->AllowedValues);
        $this->assertSame(true, $option->CaseSensitive);
        $this->assertSame(null, $option->UnknownValuePolicy);
        $this->assertSame(false, $option->AddAll);
        $this->assertSame(CliOptionVisibility::ALL, $option->Visibility);
    }

    private function getFlag(?CliOptionBuilder $option = null): CliOptionBuilder
    {
        return ($option ?: $this->getOption())
            ->long('flag')
            ->short('f')
            ->description('Description of flag')
            ->optionType(CliOptionType::FLAG);
    }

    public function testValue(): void
    {
        $option = $this
            ->getValue()
            ->load();
        $this->assertIsValue($option);
        $this->assertSame(CliOptionValueType::STRING, $option->ValueType);
        $this->assertSame(true, $option->Required);
        $this->assertSame(true, $option->WasRequired);
        $this->assertSame(true, $option->ValueRequired);
        $this->assertSame(false, $option->ValueOptional);
        $this->assertSame(false, $option->MultipleAllowed);
        $this->assertSame(false, $option->Unique);
        $this->assertSame(null, $option->Delimiter);
        $this->assertSame('today', $option->DefaultValue);
        $this->assertSame('today', $option->OriginalDefaultValue);
        $this->assertSame(null, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);
        $this->assertSame(false, $option->IsBound);
        $this->assertSame([
            'description' => 'Description of <NAME>.',
            'type' => 'string',
            'default' => 'today',
        ], $option->getJsonSchema());

        $option = $this
            ->getValue()
            ->required(false)
            ->multipleAllowed()
            ->load();
        $this->assertIsValue($option);
        $this->assertSame(CliOptionValueType::STRING, $option->ValueType);
        $this->assertSame(false, $option->Required);
        $this->assertSame(false, $option->WasRequired);
        $this->assertSame(true, $option->ValueRequired);
        $this->assertSame(false, $option->ValueOptional);
        $this->assertSame(true, $option->MultipleAllowed);
        $this->assertSame(true, $option->Unique);
        $this->assertSame(':', $option->Delimiter);
        $this->assertSame(['today'], $option->DefaultValue);
        $this->assertSame(['today'], $option->OriginalDefaultValue);
        $this->assertSame(null, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);
        $this->assertSame(false, $option->IsBound);
        $this->assertSame([
            'description' => 'Description of <NAME>.',
            'type' => 'array',
            'items' => [
                'type' => 'string',
            ],
            'uniqueItems' => true,
            'default' => ['today'],
        ], $option->getJsonSchema());

        $option = $this
            ->getValue()
            ->optionType(CliOptionType::VALUE_OPTIONAL)
            ->required(false)
            ->load();
        $this->assertIsValue($option, CliOptionType::VALUE_OPTIONAL);
        $this->assertSame(CliOptionValueType::STRING, $option->ValueType);
        $this->assertSame(false, $option->Required);
        $this->assertSame(false, $option->WasRequired);
        $this->assertSame(false, $option->ValueRequired);
        $this->assertSame(true, $option->ValueOptional);
        $this->assertSame(false, $option->MultipleAllowed);
        $this->assertSame(false, $option->Unique);
        $this->assertSame(null, $option->Delimiter);
        $this->assertSame('today', $option->DefaultValue);
        $this->assertSame('today', $option->OriginalDefaultValue);
        $this->assertSame(null, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);
        $this->assertSame(false, $option->IsBound);
        $this->assertSame([
            'description' => 'Description of <NAME>. The name applied if true or null is: today',
            'type' => [
                'string',
                'boolean',
                'null',
            ],
            'default' => false,
        ], $option->getJsonSchema());

        $constructor = (new ReflectionClass(CliOption::class))->getConstructor();

        $option = $this->getValue();
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if (
                $param->isPassedByReference() ||
                $option->issetB($name) ||
                method_exists(Builder::class, $name)
            ) {
                continue;
            }
            $value = null;
            if ($param->isDefaultValueAvailable()) {
                $value = $param->getDefaultValue();
            }
            /** @var CliOptionBuilder */
            $option = $option->$name($value);
        }
        $option = $option->load();
        $this->assertSame(false, $option->IsBound, sprintf(
            'Option bound to variable without %s::bindTo(). Check comparison with func_num_args() in %s::__construct() is >= %d',
            CliOptionBuilder::class,
            CliOption::class,
            $constructor->getNumberOfParameters(),
        ));

        $bound = null;
        $option = $this
            ->getValue()
            ->required(false)
            ->multipleAllowed()
            ->bindTo($bound)
            ->load();
        $this->assertSame(true, $option->IsBound, sprintf(
            '%s::bindTo() failed. Check comparison with func_num_args() in %s::__construct() is >= %d',
            CliOptionBuilder::class,
            CliOption::class,
            $constructor->getNumberOfParameters(),
        ));
        $this->assertNull($bound);
        $option->applyValue([]);
        $this->assertSame([], $bound);
    }

    /**
     * @param CliOptionType::* $type
     */
    private function assertIsValue(CliOption $option, int $type = CliOptionType::VALUE): void
    {
        // No assertions for:
        // - ValueType
        // - Required
        // - WasRequired
        // - MultipleAllowed
        // - Unique
        // - Delimiter
        // - DefaultValue
        // - OriginalDefaultValue
        // - EnvVariable
        // - ValueCallback
        // - IsBound
        $this->assertSame('value', $option->Name);
        $this->assertSame('value', $option->Long);
        $this->assertSame('v', $option->Short);
        $this->assertSame('v|value', $option->Key);
        $this->assertSame('NAME', $option->ValueName);
        $this->assertSame('--value', $option->DisplayName);
        $this->assertSame($type, $option->OptionType);
        $this->assertSame(false, $option->IsFlag);
        $this->assertSame(false, $option->IsOneOf);
        $this->assertSame(false, $option->IsPositional);
        $this->assertSame('Description of <NAME>', $option->Description);
        $this->assertSame(null, $option->AllowedValues);
        $this->assertSame(true, $option->CaseSensitive);
        $this->assertSame(null, $option->UnknownValuePolicy);
        $this->assertSame(false, $option->AddAll);
        $this->assertSame(CliOptionVisibility::ALL, $option->Visibility);
    }

    private function getValue(?CliOptionBuilder $option = null): CliOptionBuilder
    {
        return ($option ?: $this->getOption())
            ->long('value')
            ->short('v')
            ->valueName('NAME')
            ->unsetB('valueType')
            ->description('Description of <NAME>')
            ->optionType(CliOptionType::VALUE);
    }

    public function testOneOf(): void
    {
        $option = $this
            ->getOneOf()
            ->load();
        $this->assertIsOneOf($option);
        $this->assertSame(CliOptionValueType::DATE, $option->ValueType);
        $this->assertSame(true, $option->Required);
        $this->assertSame(true, $option->WasRequired);
        $this->assertSame(true, $option->MultipleAllowed);
        $this->assertSame(true, $option->Unique);
        $this->assertSame(':', $option->Delimiter);
        $this->assertSame(['today'], $option->DefaultValue);
        $this->assertSame(['today'], $option->OriginalDefaultValue);
        $this->assertSame(null, $option->EnvVariable);
        $this->assertSame(null, $option->ValueCallback);
        $this->assertSame(false, $option->IsBound);
        $this->assertSame([
            'description' => 'Description of items.',
            'type' => 'array',
            'items' => [
                'enum' => ['today', 'yesterday', 'tomorrow', 'ALL'],
            ],
            'uniqueItems' => true,
            'default' => ['today'],
        ], $option->getJsonSchema());
    }

    private function assertIsOneOf(CliOption $option): void
    {
        // No assertions for:
        // - ValueType
        // - Required
        // - WasRequired
        // - MultipleAllowed
        // - Unique
        // - Delimiter
        // - DefaultValue
        // - OriginalDefaultValue
        // - EnvVariable
        // - ValueCallback
        // - IsBound
        $this->assertSame('one-of', $option->Name);
        $this->assertSame('one-of', $option->Long);
        $this->assertSame('o', $option->Short);
        $this->assertSame('o|one-of', $option->Key);
        $this->assertSame('selection', $option->ValueName);
        $this->assertSame('--one-of', $option->DisplayName);
        $this->assertSame(CliOptionType::ONE_OF, $option->OptionType);
        $this->assertSame(false, $option->IsFlag);
        $this->assertSame(true, $option->IsOneOf);
        $this->assertSame(false, $option->IsPositional);
        $this->assertSame(true, $option->ValueRequired);
        $this->assertSame(false, $option->ValueOptional);
        $this->assertSame('Description of items', $option->Description);
        $this->assertSame(['today', 'yesterday', 'tomorrow', 'ALL'], $option->AllowedValues);
        $this->assertSame(true, $option->CaseSensitive);
        $this->assertSame(CliOptionValueUnknownPolicy::ACCEPT, $option->UnknownValuePolicy);
        $this->assertSame(true, $option->AddAll);
        $this->assertSame(CliOptionVisibility::ALL, $option->Visibility);
    }

    private function getOneOf(?CliOptionBuilder $option = null): CliOptionBuilder
    {
        return ($option ?: $this->getOption())
            ->long('one-of')
            ->short('o')
            ->valueName('selection')
            ->description('Description of items')
            ->optionType(CliOptionType::ONE_OF)
            ->multipleAllowed();
    }

    private function getOption(): CliOptionBuilder
    {
        return CliOption::build($this->getContainer())
            ->valueType(CliOptionValueType::DATE)
            ->allowedValues(['today', 'yesterday', 'tomorrow'])
            ->unknownValuePolicy(CliOptionValueUnknownPolicy::ACCEPT)
            ->required()
            ->unique()
            ->addAll()
            ->defaultValue('today')
            ->delimiter(':');
    }

    private function getContainer(): ContainerInterface
    {
        return new Container();
    }
}
