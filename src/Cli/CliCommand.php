<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Console\Console;
use Lkrms\Container\CliAppContainer;
use Lkrms\Contract\IBound;
use Lkrms\Exception\InvalidCliArgumentException;
use Lkrms\Util\Convert;
use RuntimeException;
use UnexpectedValueException;

/**
 * Base class for CLI commands
 *
 */
abstract class CliCommand implements IBound
{
    /**
     * Return a short description of the command
     *
     * @return string
     */
    abstract protected function _getDescription(): string;

    /**
     * Return a list of CliOption objects and/or arrays to create them from
     *
     * The following return values are equivalent:
     *
     * ```php
     * // 1.
     * [
     *   new CliOption(
     *     "dest", "d", "DIR", "Sync files to DIR", CliOptionType::VALUE, null, true
     *   ),
     * ]
     *
     * // 2.
     * [
     *   [
     *     "long"        => "dest",
     *     "short"       => "d",
     *     "valueName"   => "DIR",
     *     "description" => "Sync files to DIR",
     *     "optionType"  => CliOptionType::VALUE,
     *     "required"    => true,
     *   ],
     * ]
     * ```
     *
     * @return array<int,CliOption|array>
     * @see \Lkrms\Concern\TConstructible::from()
     */
    abstract protected function _getOptions(): array;

    /**
     * Run the command, optionally returning an exit status
     *
     * PHP's exit status will be:
     * 1. the return value of this method (if an `int` is returned)
     * 2. the last value passed to {@see CliCommand::setExitStatus()}, or
     * 3. `0`, indicating success, unless an unhandled error occurs
     *
     * @param string ...$params
     * @return int|void
     */
    abstract protected function run(string ...$params);

    /**
     * @var CliAppContainer
     */
    private $Container;

    /**
     * @var int
     */
    private $ExitStatus = 0;

    /**
     * @var string[]|null
     */
    private $Name;

    /**
     * @var CliOption[]|null
     */
    private $Options;

    /**
     * @var array<string,CliOption>
     */
    private $OptionsByName = [];

    /**
     * @var array<string,CliOption>
     */
    private $OptionsByKey = [];

    /**
     * @var array<string,CliOption>
     */
    private $HiddenOptionsByKey = [];

    /**
     * @var string[]|null
     */
    private $Arguments;

    /**
     * @var array<string,string|array|bool|null>|null
     */
    private $OptionValues;

    /**
     * @var int|null
     */
    private $OptionErrors;

    /**
     * @var int|null
     */
    private $NextArgumentIndex;

    /**
     * @var bool
     */
    private $IsHelp = false;

    /**
     * @var bool
     */
    private $HasRun = false;

    public function __construct(CliAppContainer $container)
    {
        $this->Container = $container;
    }

    final public function container(): CliAppContainer
    {
        return $this->Container;
    }

    /**
     * @internal
     * @param string[] $name
     */
    final public function setName(array $name): void
    {
        if (!is_null($this->Name))
        {
            throw new RuntimeException("Name already set");
        }

        $this->Name = $name;
    }

    /**
     * Get the command name as a string of space-delimited subcommands
     *
     * @return string
     */
    final public function getCommandName(): string
    {
        return implode(" ", $this->getName());
    }

    /**
     * Get the command name, including the name used to run the script
     *
     * @return string
     */
    final public function getLongCommandName(): string
    {
        return implode(" ", $this->getLongName());
    }

    /**
     * Get the command name as an array of subcommands
     *
     * @return string[]
     */
    final public function getName(): array
    {
        return $this->Name ?: [];
    }

    /**
     * Get the command name, including the name used to run the script, as an
     * array
     *
     * @return string[]
     */
    final public function getLongName(): array
    {
        $name = $this->getName();
        array_unshift($name, $this->container()->getProgramName());

        return $name;
    }

    /**
     * Get a short description of the command
     *
     * @return string
     */
    final public function getDescription(): string
    {
        return $this->_getDescription();
    }

    /**
     *
     * @param CliOption|array $option
     * @param array $options
     * @param bool $hide
     */
    private function addOption($option, array & $options, $hide = false)
    {
        if (!($option instanceof CliOption))
        {
            $option = CliOption::from($this->container(), $option);
        }

        $option->validate();

        list ($short, $long, $names) = [$option->Short, $option->Long, []];

        if ($short)
        {
            $names[] = $short;
        }

        if ($long)
        {
            $names[] = $long;
        }

        if (!empty(array_intersect($names, array_keys($this->OptionsByName))))
        {
            throw new UnexpectedValueException("Option names must be unique: " . implode(", ", $names));
        }

        $options[] = $option;

        foreach ($names as $key)
        {
            $this->OptionsByName[$key] = $option;
        }

        $this->OptionsByKey[$option->Key] = $option;

        if ($hide)
        {
            $this->HiddenOptionsByKey[$option->Key] = $option;
        }
    }

    private function loadOptions()
    {
        if (!is_null($this->Options))
        {
            return;
        }

        $_options = $this->_getOptions();
        $options  = [];

        foreach ($_options as $option)
        {
            $this->addOption($option, $options);
        }

        if (!array_key_exists("help", $this->OptionsByName))
        {
            $this->addOption([
                "long"  => "help",
                "short" => array_key_exists("h", $this->OptionsByName) ? null : "h"
            ], $options, true);
        }

        $this->Options = $options;
    }

    /**
     *
     * @return CliOption[]
     */
    final public function getOptions(): array
    {
        $this->loadOptions();

        return $this->Options;
    }

    final public function hasOption(string $name): bool
    {
        $this->loadOptions();

        return !is_null($this->OptionsByName[$name] ?? null);
    }

    final public function getOption(string $name): ?CliOption
    {
        $this->loadOptions();

        return $this->OptionsByName[$name] ?? null;
    }

    final public function getUsage(bool $line1 = false): string
    {
        $options = "";

        // To produce a one-line summary like this:
        //
        //     sync [-ny] [--verbose] [--exclude PATTERN] --from SOURCE --to DEST
        //
        // We need values like this:
        //
        //     $shortFlag = ['n', 'y'];
        //     $longFlag  = ['verbose'];
        //     $optional  = ['--exclude PATTERN'];
        //     $required  = ['--from SOURCE', '--to DEST'];
        //
        $shortFlag = [];
        $longFlag  = [];
        $optional  = [];
        $required  = [];

        foreach ($this->getOptions() as $option)
        {
            if (array_key_exists($option->Key, $this->HiddenOptionsByKey))
            {
                continue;
            }

            list ($short, $long, $line, $value, $valueName, $list) = [$option->Short, $option->Long, [], [], "", ""];

            if ($option->IsFlag)
            {
                if ($short)
                {
                    $line[]      = "-{$short}";
                    $shortFlag[] = $short;
                }

                if ($long)
                {
                    $line[] = "--{$long}";

                    if (!$short)
                    {
                        $longFlag[] = $long;
                    }
                }
            }
            else
            {
                $valueName = $option->ValueName;

                if ($valueName != strtoupper($valueName))
                {
                    $valueName = "<" . Convert::toKebabCase($valueName) . ">";
                }

                if ($option->MultipleAllowed && $option->Delimiter)
                {
                    $list = "{$option->Delimiter}...";
                }

                if ($short)
                {
                    $line[]  = "-{$short}";
                    $value[] = $option->IsValueRequired ? " $valueName$list" : "[$valueName$list]";
                }

                if ($long)
                {
                    $line[]  = "--{$long}";
                    $value[] = $option->IsValueRequired ? " $valueName$list" : "[=$valueName$list]";
                }

                if ($option->IsRequired)
                {
                    $required[] = $line[0] . $value[0];
                }
                else
                {
                    $optional[] = $line[0] . $value[0];
                }
            }

            if (!$line1)
            {
                // Format:
                //
                //     _-o, --option_[=__VALUE__]
                //       Option description
                //         default: ___auto___
                //         options:
                //         - _option1_
                //         - _option2_
                //         - _option3_
                $sep      = ($option->Description ? "\n      " : "\n    ");
                $options .= ("\n  _" . implode(", ", $line) . "_"
                    . str_replace($valueName, "__" . $valueName . "__", (array_pop($value) ?: ""))
                    . ($option->Description ? "\n    " . $option->Description : "")
                    . ((!$option->IsFlag && $option->DefaultValue) ? $sep . "default: ___" . implode(",", Convert::toArray($option->DefaultValue)) . "___" : "")
                    . ($option->AllowedValues ? $sep . "options:" . $sep . "- _" . implode("_" . $sep . "- _", $option->AllowedValues) . "_" : "")) . "\n";
            }
        }

        $synopsis = (($shortFlag ? " [-" . implode("", $shortFlag) . "]" : "")
            . ($longFlag ? " [--" . implode("] [--", $longFlag) . "]" : "")
            . ($optional ? " [" . implode("] [", $optional) . "]" : "")
            . ($required ? " " . implode(" ", $required) : ""));

        $name        = $this->getLongCommandName();
        $desc        = $this->getDescription();
        if ($options = trim($options, "\n"))
        {
            $options = <<<EOF


___OPTIONS___
$options
EOF;
        };

        return $line1 ? $synopsis : <<<EOF
___NAME___
  __{$name}__

___DESCRIPTION___
  {$desc}

___SYNOPSIS___
  __{$name}__{$synopsis}${options}
EOF;
    }

    private function optionError(string $message)
    {
        Console::error($this->getLongCommandName() . ": $message");
        $this->OptionErrors++;
    }

    private function loadOptionValues()
    {
        if (!is_null($this->OptionValues))
        {
            return;
        }

        $this->loadOptions();
        $this->OptionErrors = 0;

        $args   = $this->Arguments;
        $merged = [];

        for ($i = 0; $i < count($args); $i++)
        {
            list ($arg, $short, $matches) = [$args[$i], false, null];

            if (preg_match("/^-([0-9a-z])(.*)/i", $arg, $matches))
            {
                $name  = $matches[1];
                $value = $matches[2] ?: null;
                $short = true;
            }
            elseif (preg_match("/^--([0-9a-z_-]+)(=(.*))?\$/i", $arg, $matches))
            {
                $name  = $matches[1];
                $value = ($matches[2] ?? null) ? $matches[3] : null;
            }
            else
            {
                if ($arg == "--")
                {
                    $i++;
                }
                elseif (substr($arg, 0, 1) == "-")
                {
                    $this->optionError("invalid argument '$arg'");

                    continue;
                }

                break;
            }

            $option = $this->OptionsByName[$name] ?? null;

            if (is_null($option))
            {
                $this->optionError("unknown option '$name'");

                continue;
            }
            elseif ($option->IsFlag)
            {
                // Handle multiple short flags per argument, e.g. `cp -rv`
                if ($short && $value)
                {
                    $args[$i] = "-$value";
                    $i--;
                }

                $value = true;
            }
            elseif (!$option->IsValueRequired)
            {
                $value = $value ?: $option->DefaultValue ?: "";
            }
            elseif (is_null($value))
            {
                $i++;

                if (is_null($value = ($args[$i] ?? null)))
                {
                    // Allow null to be stored to prevent an additional
                    // "argument required" error
                    $this->optionError("{$option->DisplayName} value required");
                    $i--;
                }
            }

            if ($option->MultipleAllowed &&
                $option->Delimiter &&
                $value && is_string($value))
            {
                $value = explode($option->Delimiter, $value);
            }

            $key = $option->Key;

            if (isset($merged[$key]))
            {
                $merged[$key] = array_merge(Convert::toArray($merged[$key]), Convert::toArray($value));
            }
            else
            {
                $merged[$key] = $value;
            }
        }

        $this->NextArgumentIndex = $i;

        foreach ($merged as $key => $value)
        {
            $option = $this->OptionsByKey[$key];

            if ($option->Long == "help")
            {
                $this->IsHelp = true;

                continue;
            }

            if (!$option->MultipleAllowed && is_array($value))
            {
                $this->optionError("{$option->DisplayName} cannot be used multiple times");
            }

            if (!is_null($option->AllowedValues) && !is_null($value) &&
                !empty($invalid = array_diff(Convert::toArray($value), $option->AllowedValues)))
            {
                $this->optionError("invalid {$option->DisplayName} "
                    . Convert::numberToNoun(count($invalid), "value") . ": " . implode(", ", $invalid));
            }
        }

        foreach ($this->OptionsByKey as $option)
        {
            if ($option->IsRequired && !array_key_exists($option->Key, $merged))
            {
                if (!(count($args) == 1 && $this->IsHelp))
                {
                    $this->optionError("{$option->DisplayName} argument required");
                }
            }
            else
            {
                $value = $merged[$option->Key] ?? (!$option->IsValueRequired ? null : $option->DefaultValue);

                if ($option->IsFlag && $option->MultipleAllowed)
                {
                    $value = is_null($value) ? 0 : count(Convert::toArray($value));
                }
                elseif ($option->MultipleAllowed)
                {
                    $value = is_null($value) ? [] : Convert::toArray($value);
                }

                $option->setValue($value);
            }
        }

        if ($this->OptionErrors)
        {
            throw new InvalidCliArgumentException();
        }

        $this->OptionValues = $merged;
    }

    /**
     * Get the value of a command line option
     *
     * For values that can be given multiple times, an array of values will be
     * returned. For flags that can be given multiple times, the number of uses
     * will be returned.
     *
     * @param string $name Either the `Short` or `Long` name of the option
     * @return string|string[]|bool|int|null
     */
    final public function getOptionValue(string $name)
    {
        $this->assertHasRun();

        if (!($option = $this->getOption($name)))
        {
            throw new UnexpectedValueException("No option with name '$name'");
        }

        return $option->Value;
    }

    /**
     * @return array<string,string|string[]|bool|int|null>
     */
    final public function getAllOptionValues(): array
    {
        $this->assertHasRun();

        $values = [];
        foreach ($this->Options as $option)
        {
            $name          = $option->Long ?: $option->Short;
            $values[$name] = $option->Value;
        }
        return $values;
    }

    /**
     * @param string|CliOption $option
     */
    final public function getEffectiveArgument($option, bool $shellEscape = false): ?string
    {
        if (is_string($option))
        {
            $this->assertHasRun();

            if (!($option = $this->getOption($option)))
            {
                throw new UnexpectedValueException("No option with name '$option'");
            }
        }
        if (is_null($option->Value) || $option->Value === false || $option->Value === 0)
        {
            return null;
        }
        if (is_int($option->Value))
        {
            if ($option->Short)
            {
                return "-" . str_repeat($option->Short, $option->Value);
            }
            return implode(" ", array_fill(0, $option->Value, "--{$option->Long}"));
        }
        $value = null;
        if (is_array($option->Value))
        {
            $value = implode(",", $option->Value);
        }
        elseif (is_string($option->Value))
        {
            $value = $option->Value;
        }
        if ($shellEscape && !is_null($value))
        {
            $value = escapeshellarg($value);
        }
        return $option->Long
            ? "--{$option->Long}" . (is_null($value) ? "" : "=$value")
            : "-{$option->Short}" . $value;
    }

    /**
     * @return string[]
     */
    final public function getEffectiveCommandLine(bool $shellEscape = false): array
    {
        $this->assertHasRun();

        $args = $this->getLongName();
        foreach ($this->Options as $option)
        {
            $args[] = $this->getEffectiveArgument($option, $shellEscape);
        }
        return array_values(array_filter($args, fn($arg) => !is_null($arg)));
    }

    private function assertHasRun()
    {
        if (!$this->HasRun)
        {
            throw new RuntimeException("Command must be invoked first");
        }
    }

    /**
     * Parse the given arguments and run the command
     *
     * @param string[] $args
     * @return int
     * @see CliCommand::run()
     */
    final public function __invoke(array $args): int
    {
        if ($this->HasRun)
        {
            throw new RuntimeException("Command has already run");
        }

        $this->Arguments = $args;
        $this->loadOptionValues();

        if ($this->IsHelp)
        {
            Console::printTo($this->getUsage(), ...Console::getOutputTargets());
            return 0;
        }

        $this->HasRun = true;

        $return = $this->run(...array_slice($this->Arguments, $this->NextArgumentIndex));

        if (is_int($return))
        {
            return $return;
        }

        return $this->ExitStatus;
    }

    /**
     * Set the command's return value / exit status
     *
     * @param int $status
     * @see CliCommand::run()
     */
    final protected function setExitStatus(int $status)
    {
        $this->ExitStatus = $status;
    }

    /**
     * Get the current return value / exit status
     *
     * @return int
     * @see CliCommand::setExitStatus()
     */
    final protected function getExitStatus(): int
    {
        return $this->ExitStatus;
    }

    /**
     * @deprecated Use {@see CliCommand::getOption()} instead
     */
    final public function getOptionByName(string $name): ?CliOption
    {
        return $this->getOption($name);
    }
}
