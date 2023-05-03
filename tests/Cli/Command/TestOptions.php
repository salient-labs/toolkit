<?php declare(strict_types=1);

namespace Lkrms\Tests\Cli\Command;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Facade\Convert;

class TestOptions extends CliCommand
{
    public $flag1;

    public $valPos1;

    public $val1;

    public $valOpt1;

    public $oneOfPos1;

    public $oneOf1;

    public $oneOfOpt3;

    public function getShortDescription(): string
    {
        return 'Test various permutations of each CliOption type';
    }

    protected function getOptionList(): array
    {
        $short = array_map(
            fn(int $ord): string => chr($ord),
            [...range(ord('a'), ord('z')), ...range(ord('A'), ord('Z'))]
        );
        natcasesort($short);

        $options = [];
        $current = 1;
        foreach (
            [
                'FLAG' => CliOptionType::FLAG,
                'VALUE' => CliOptionType::VALUE,
                'VALUE_OPTIONAL' => CliOptionType::VALUE_OPTIONAL,
                'VALUE_POSITIONAL' => CliOptionType::VALUE_POSITIONAL,
                'ONE_OF' => CliOptionType::ONE_OF,
                'ONE_OF_OPTIONAL' => CliOptionType::ONE_OF_OPTIONAL,
                'ONE_OF_POSITIONAL' => CliOptionType::ONE_OF_POSITIONAL,
            ] as $typeName => $type
        ) {
            unset($names);
            $vary = [];
            $apply = [];

            switch ($type) {
                case CliOptionType::FLAG:
                    $names = ['flag1', 'flag2'];
                    $vary['multipleAllowed'] = [false, true];
                    $vary['bindTo'] = [&$this->flag1];
                    break;

                case CliOptionType::VALUE_POSITIONAL:
                    $names = ['valPos1'];
                    $vary['required'] = [true];
                    $vary['bindTo'] = [&$this->valPos1];
                case CliOptionType::VALUE:
                    $names ??= ['val1', 'val2', 'val3'];
                    $vary['required'] ??= [false, true];
                    $vary['bindTo'] ??= [&$this->val1];
                case CliOptionType::VALUE_OPTIONAL:
                    $names ??= ['valOpt1', 'valOpt2', 'valOpt3'];
                    $vary['valueName'] = ['VAL', 'val', null];
                    $vary['multipleAllowed'] = [false, false, true];
                    $vary['bindTo'] ??= [&$this->valOpt1];
                    break;

                case CliOptionType::ONE_OF_POSITIONAL:
                    $names = ['oneOfPos1', 'oneOfPos2', 'oneOfPos3'];
                    $vary['defaultValue'] = [];
                    $vary['required'] = [true];
                    $vary['bindTo'] = [&$this->oneOfPos1];
                case CliOptionType::ONE_OF:
                    $names ??= ['oneOf1', 'oneOf2', 'oneOf3', 'oneOf4'];
                    $vary['defaultValue'] ??= [null, 'value1', 'value1,value2', ['value4', 'value3']];
                    $vary['required'] ??= [false, false, true];
                    $vary['bindTo'] ??= [&$this->oneOf1];
                case CliOptionType::ONE_OF_OPTIONAL:
                    $names ??= ['oneOfOpt1', 'oneOfOpt2', 'oneOfOpt3', 'oneOfOpt4'];
                    $vary['valueName'] = ['ONE_OF', 'one_of', null, 'one_of'];
                    $vary['multipleAllowed'] = [false, false, true, true];
                    $vary['addAll'] = [false, false, true, true];
                    $vary['defaultValue'] ??= [null, 'value2', 'value3,value4', ['value1', 'value4']];
                    $vary['bindTo'] ??= [null, null, &$this->oneOfOpt3];

                    $apply['allowedValues'] = ['value1', 'value2', 'value3', 'value4'];
                    break;
            }

            foreach ($names ?? [$typeName . $current] as $i => $long) {
                $option = CliOption::build()
                    ->long($long)
                    ->short(array_shift($short))
                    ->optionType($type);
                $desc = [];
                foreach ($vary as $property => $values) {
                    if (!array_key_exists($i, $values)) {
                        continue;
                    }
                    // Preserve references, i.e. bindTo arguments
                    $value = &$values[$i];
                    $option = $option->{$property}($value);
                    $desc[] = "$property=***" . Convert::valueToCode($value) . '***';
                    unset($value);
                }

                foreach ($apply as $property => $value) {
                    $option = $option->{$property}($value);
                    $desc[] = "~~$property=***" . Convert::valueToCode($value) . '***~~';
                }

                $option = $option->description(implode(
                    "  \n",
                    ["Option $current (CliOptionType::$typeName)  \n", ...$desc]
                ));

                $options[] = $option;
                $current++;
            }
        }

        return $options;
    }

    public function getLongDescription(): ?string
    {
        return <<<EOF
            Variations tested:

            - Short option names that vary only by case  
            - UPPER_CASE, lower_case and null `valueName`  
            - Legal combinations of `multipleAllowed` and `required`  
            - Bound to class properties, and unbound  
            EOF;
    }

    public function getHelpSections(): ?array
    {
        return null;
    }

    protected function run(string ...$args)
    {
        if ($this->app()->getRunningCommand() === $this) {
            echo json_encode(
                [
                    'args' => $args,
                    'options' => $this->getOptionValues(),
                    'bound' => [
                        'flag1' => $this->flag1,
                        'valPos1' => $this->valPos1,
                        'val1' => $this->val1,
                        'valOpt1' => $this->valOpt1,
                        'oneOfPos1' => $this->oneOfPos1,
                        'oneOf1' => $this->oneOf1,
                        'oneOfOpt3' => $this->oneOfOpt3,
                    ]
                ],
                JSON_PRETTY_PRINT
            );
        }
    }
}
