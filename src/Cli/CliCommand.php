<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Assert;
use Lkrms\Console\Console;
use Lkrms\Convert;
use Lkrms\Exception\InvalidCliArgumentException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Base class for CLI commands
 *
 * @package Lkrms
 */
abstract class CliCommand
{
    /**
     * Return a short description of the command
     *
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Return the command name as an array of subcommands
     *
     * A command could return the following, for example:
     *
     * ```php
     * ["sync", "canvas", "from-sis"]
     * ```
     *
     * to register itself as the handler for:
     *
     * ```
     * my-cli-app sync canvas from-sis
     * ```
     *
     * Valid subcommands start with a letter, followed by any number of letters,
     * numbers, hyphens, or underscores.
     *
     * @return string[] An `UnexpectedValueException` will be thrown if an
     * invalid subcommand is returned.
     */
    abstract protected function _getName(): array;

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
     * @see \Lkrms\Template\TConstructible::fromArray()
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
     * @var int
     */
    private $ExitStatus = 0;

    /**
     * @var string[]
     */
    private $Name;

    /**
     * @var CliOption[]
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
     * @var string[]
     */
    private $Arguments = [];

    /**
     * @var array<string,string|array|bool|null>
     */
    private $OptionValues;

    /**
     * @var int
     */
    private $OptionErrors;

    /**
     * @var int
     */
    private $NextArgumentIndex;

    /**
     * @var bool
     */
    private $IsHelp = false;

    final public static function assertNameIsValid(?array $name)
    {
        foreach ($name as $i => $subcommand)
        {
            Assert::pregMatch($subcommand, '/^[a-zA-Z][a-zA-Z0-9_-]*$/', "name[$i]");
        }
    }

    final public function __construct()
    {
    }

    /**
     * Create an instance of the command and register it
     *
     * The following statements are equivalent:
     *
     * ```php
     * // 1.
     * MyCliCommand::register();
     *
     * // 2.
     * Cli::registerCommand(new MyCliCommand());
     * ```
     *
     * But the only way to register a command with an application-specific name
     * is with `CliCommand::register()`:
     *
     * ```php
     * MyCliCommand::register(["subcommand", "my-cli-command"]);
     * ```
     *
     * @param array|null $name If set, the name returned by the command will be
     * ignored.
     */
    final public static function register(array $name = null)
    {
        $command = new static();

        if (!is_null($name))
        {
            self::assertNameIsValid($name);
            $command->Name = $name;
        }

        Cli::registerCommand($command);
    }

    final public function getCommandName(): string
    {
        return implode(" ", $this->getName());
    }

    final public function getLongCommandName(): string
    {
        $name = $this->getName();
        array_unshift($name, Cli::getProgramName());

        return implode(" ", $name);
    }

    /**
     *
     * @return string[]
     */
    final public function getName(): array
    {
        if (is_null($this->Name))
        {
            self::assertNameIsValid($name = $this->_getName());
            $this->Name = $name;
        }

        return $this->Name;
    }

    /**
     *
     * @param CliOption|array $option
     * @param array|null $options
     * @param bool $hide
     */
    private function addOption($option, array & $options = null, $hide = false)
    {
        if (!($option instanceof CliOption))
        {
            $option = CliOption::fromArray($option);
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

        if (!is_null($options))
        {
            $options[] = $option;
        }

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

    final public function getOptionByName(string $name): ?CliOption
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

            list ($short, $long, $line, $value, $valueName) = [$option->Short, $option->Long, [], [], ""];

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

                if ($short)
                {
                    $line[]  = "-{$short}";
                    $value[] = $option->IsValueRequired ? " $valueName" : "[$valueName]";
                }

                if ($long)
                {
                    $line[]  = "--{$long}";
                    $value[] = $option->IsValueRequired ? " $valueName" : "[=$valueName]";
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

        $name    = $this->getLongCommandName();
        $desc    = $this->getDescription();
        $options = trim($options, "\n");

        return $line1 ? $synopsis :
<<<EOF
___NAME___
  __{$name}__

___DESCRIPTION___
  {$desc}

___SYNOPSIS___
  __{$name}__{$synopsis}

___OPTIONS___
$options
EOF;
    }

    final protected function optionError(string $message)
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
            elseif ($option->IsValueRequired)
            {
                if (is_null($value))
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
        if (!($option = $this->getOptionByName($name)))
        {
            throw new UnexpectedValueException("No option with name '$name'");
        }

        $this->loadOptionValues();

        return $option->Value;
    }

    /**
     *
     * @return array<string,string|string[]|bool|int|null>
     */
    final public function getAllOptionValues(): array
    {
        $this->loadOptionValues();

        $values = [];

        foreach ($this->Options as $option)
        {
            $name          = $option->Long ?: $option->Short;
            $values[$name] = $option->Value;
        }

        return $values;
    }

    final public function __invoke(array $args): int
    {
        $this->Arguments = $args;
        $this->loadOptionValues();

        if ($this->IsHelp)
        {
            Console::printTo($this->getUsage(), ...Console::getOutputTargets());

            return 0;
        }

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
}
