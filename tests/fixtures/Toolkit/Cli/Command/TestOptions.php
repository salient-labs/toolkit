<?php declare(strict_types=1);

namespace Salient\Tests\Cli\Command;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliCommand;
use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Cli\CliOptionValueType;
use Salient\Utility\Arr;
use Salient\Utility\Env;
use Salient\Utility\Json;
use Salient\Utility\Str;
use DateTimeInterface;

class TestOptions extends CliCommand
{
    private const ACTION_APPLY_VALUES = 'apply-values';
    private const ACTION_APPLY_SCHEMA_VALUES = 'apply-schema-values';
    private const ACTION_GET_RUNNING_COMMAND = 'get-running-command';

    /** @var mixed */
    public $Result;

    // --

    private ?string $Action = null;
    private ?string $Data = null;
    /** @var string[] */
    private array $Print = [];
    private bool $Flag = false;
    private int $RepeatableFlag = 0;
    private ?bool $NullableFlag = null;
    private string $Value = '';
    /** @var string[]|null */
    private ?array $RepeatableValue = null;
    private ?DateTimeInterface $RequiredValue = null;
    private ?string $OptionalValue = null;

    // --

    /** @var string[] */
    private array $Args;

    public function description(): string
    {
        return 'Test CliCommand options';
    }

    protected function getOptionList(): array
    {
        $required = Arr::toIndex(
            Str::split(',', Env::get('required', ''))
        );
        $positional = Env::getBool('positional', false);

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
                ->long('action')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues([
                    self::ACTION_APPLY_VALUES,
                    self::ACTION_APPLY_SCHEMA_VALUES,
                    self::ACTION_GET_RUNNING_COMMAND,
                ])
                ->hide()
                ->bindTo($this->Action),
            CliOption::build()
                ->long('data')
                ->optionType(CliOptionType::VALUE)
                ->hide()
                ->bindTo($this->Data),
            CliOption::build()
                ->long('print')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys($this->getCallbacks()))
                ->multipleAllowed()
                ->unique()
                ->hide()
                ->bindTo($this->Print),
            CliOption::build()
                ->long('flag')
                ->short('f')
                ->description('Flag')
                ->inSchema()
                ->bindTo($this->Flag),
            CliOption::build()
                ->long('flags')
                ->short('F')
                ->description('Flag with multipleAllowed()')
                ->multipleAllowed()
                ->inSchema()
                ->bindTo($this->RepeatableFlag),
            CliOption::build()
                ->long('nullable')
                ->description('Flag with nullable() and no short form')
                ->nullable()
                ->inSchema()
                ->bindTo($this->NullableFlag),
            CliOption::build()
                ->long('value')
                ->short('v')
                ->valueName('entity')
                ->description('Value with defaultValue() and valueName <entity>')
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('foo')
                ->inSchema()
                ->bindTo($this->Value),
            CliOption::build()
                ->long('values')
                ->short('V')
                ->description('Value with multipleAllowed(), unique() and nullable()')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->unique()
                ->nullable()
                ->inSchema()
                ->bindTo($this->RepeatableValue),
            CliOption::build()
                ->long('start')
                ->short('s')
                ->valueName('date')
                ->description('Value with conditional required(), valueType DATE and valueName <date>')
                ->optionType(CliOptionType::VALUE)
                ->valueType(CliOptionValueType::DATE)
                ->required($required['start'] ?? false)
                ->inSchema()
                ->bindTo($this->RequiredValue),
            CliOption::build()
                ->long('filter-regex')
                ->short('r')
                ->valueName('pattern')
                ->description('VALUE_OPTIONAL with valueName <pattern> and a default value')
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->defaultValue('/./')
                ->inSchema()
                ->bindTo($this->OptionalValue),
            ...($positional
                ? [
                    CliOption::build()
                        ->long('file')
                        ->valueName('INPUT_FILE')
                        ->description('required() VALUE_POSITIONAL with valueType FILE and valueName "INPUT_FILE"')
                        ->optionType(CliOptionType::VALUE_POSITIONAL)
                        ->valueType(CliOptionValueType::FILE)
                        ->required(),
                    CliOption::build()
                        ->name('uri')
                        ->valueName('endpoint_uri')
                        ->description('required() VALUE_POSITIONAL with valueName "endpoint_uri"')
                        ->optionType(CliOptionType::VALUE_POSITIONAL)
                        ->required(),
                    CliOption::build()
                        ->name('property')
                        ->valueName('<key>=<VALUE>')
                        ->description('VALUE_POSITIONAL with multipleAllowed() and valueName "\<key>=\<VALUE>"')
                        ->optionType(CliOptionType::VALUE_POSITIONAL)
                        ->multipleAllowed(),
                ]
                : []),
        ];
    }

    protected function run(string ...$args)
    {
        $this->Args = $args;

        switch ($this->Action) {
            case self::ACTION_APPLY_VALUES:
                /** @var string */
                $data = $this->Data;
                $data = Json::parseObjectAsArray($data);
                if (!is_array($data) || !$this->checkOptionValues($data)) {
                    throw new CliInvalidArgumentsException('Invalid option values');
                }
                $this->applyOptionValues($data, true, true, false, true, true);
                break;

            case self::ACTION_APPLY_SCHEMA_VALUES:
                /** @var string */
                $data = $this->Data;
                $data = Json::parseObjectAsArray($data);
                if (!is_array($data) || !$this->checkOptionValues($data)) {
                    throw new CliInvalidArgumentsException('Invalid option values');
                }
                $this->applyOptionValues($data, true, true, true, true, true);
                break;

            case self::ACTION_GET_RUNNING_COMMAND:
                $this->Result = $this->App->getRunningCommand();
                break;
        }

        $callbacks = $this->getCallbacks();
        foreach ($this->Print as $print) {
            $output[$print] = $callbacks[$print]();
        }

        if (!isset($output)) {
            return;
        }

        echo Json::prettyPrint($output) . \PHP_EOL;
    }

    /**
     * @return array<string,callable():mixed>
     */
    private function getCallbacks(): array
    {
        return [
            'args' => fn() => $this->Args,
            'allOptions' => fn() => $this->removeHidden($this->getOptionValues()),
            'options' => fn() => $this->removeHidden($this->getOptionValues(true)),
            'schemaOptions' => fn() => $this->getOptionValues(true, true, true),
            'hasArg' => function () {
                foreach ($this->getOptions() as $option) {
                    /** @var string */
                    $name = $option->Name;
                    if ($this->optionHasArgument($name)) {
                        $hasArg[$option->Name] = true;
                    }
                }
                return $this->removeHidden($hasArg ?? []);
            },
            'bound' => fn() => [
                'Flag' => $this->Flag,
                'RepeatableFlag' => $this->RepeatableFlag,
                'NullableFlag' => $this->NullableFlag,
                'Value' => $this->Value,
                'RepeatableValue' => $this->RepeatableValue,
                'RequiredValue' => $this->RequiredValue,
                'OptionalValue' => $this->OptionalValue,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    private function removeHidden(array $values): array
    {
        unset($values['action']);
        unset($values['data']);
        unset($values['print']);
        return $values;
    }
}
