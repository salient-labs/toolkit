<?php declare(strict_types=1);

namespace Lkrms\Tests\Cli\Command;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Catalog\CliOptionValueType;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Utility\Json;
use DateTimeInterface;

class TestOptions extends CliCommand
{
    private bool $Flag = false;

    private int $RepeatableFlag = 0;

    private ?bool $NullableFlag = null;

    private string $Value = '';

    /**
     * @var string[]|null
     */
    private ?array $RepeatableValue = null;

    private ?DateTimeInterface $RequiredValue = null;

    public function description(): string
    {
        return 'Test CliCommand options';
    }

    protected function getOptionList(): array
    {
        return [
            // CliOption::build()
            //     ->name()
            //     ->long()
            //     ->short()
            //     ->valueName()
            //     ->description()
            //     ->optionType()
            //     ->valueType()
            //     ->allowedValues()
            //     ->unknownValuePolicy()
            //     ->required()
            //     ->multipleAllowed()
            //     ->unique()
            //     ->addAll()
            //     ->defaultValue()
            //     ->nullable()
            //     ->envVariable()
            //     ->delimiter()
            //     ->valueCallback()
            //     ->visibility()
            //     ->hide()
            //     ->bindTo(),
            CliOption::build()
                ->long('flag')
                ->short('f')
                ->description('Flag')
                ->bindTo($this->Flag),
            CliOption::build()
                ->long('flags')
                ->short('F')
                ->description('Flag with multipleAllowed()')
                ->multipleAllowed()
                ->bindTo($this->RepeatableFlag),
            CliOption::build()
                ->long('nullable')
                ->description('Flag with nullable() and no short form')
                ->nullable()
                ->bindTo($this->NullableFlag),
            CliOption::build()
                ->long('value')
                ->short('v')
                ->valueName('entity')
                ->description('Value with defaultValue() and valueName <entity>')
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('foo')
                ->bindTo($this->Value),
            CliOption::build()
                ->long('values')
                ->short('V')
                ->description('Value with multipleAllowed(), unique() and nullable()')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->unique()
                ->nullable()
                ->bindTo($this->RepeatableValue),
            CliOption::build()
                ->long('start')
                ->short('s')
                ->valueName('date')
                ->description('Value with required(), valueType DATE and valueName <date>')
                ->optionType(CliOptionType::VALUE)
                ->valueType(CliOptionValueType::DATE)
                ->required()
                ->bindTo($this->RequiredValue),
        ];
    }

    protected function run(string ...$args)
    {
        foreach ($this->getOptions() as $option) {
            if ($this->optionHasArgument($option->Name)) {
                $hasArg[$option->Name] = true;
            }
        }

        echo Json::prettyPrint([
            'args' => $args,
            'options' => $this->getOptionValues(true),
            'bound' => [
                'Flag' => $this->Flag,
                'RepeatableFlag' => $this->RepeatableFlag,
                'NullableFlag' => $this->NullableFlag,
                'Value' => $this->Value,
                'RepeatableValue' => $this->RepeatableValue,
                'RequiredValue' => $this->RequiredValue,
            ],
            'hasArg' => $hasArg ?? [],
        ]) . \PHP_EOL;
    }
}
