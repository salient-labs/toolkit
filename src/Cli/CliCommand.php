<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Console\Console;
use Lkrms\Convert;
use RuntimeException;
use UnexpectedValueException;

/**
 *
 * @package Lkrms
 */
abstract class CliCommand
{
    /**
     * Return the name of the command and any parent commands
     *
     * A subclass could return the following, for example:
     *
     * ```php
     * ["data", "canvas", "sync-from-sis"]
     * ```
     *
     * to register itself as the handler for:
     *
     * ```
     * my-cli-app data canvas sync-from-sis
     * ```
     *
     * @return array<int,string> Must contain at least one element, and elements
     * must match the regular expression `^[a-zA-Z][a-zA-Z0-9_-]*$`.
     */
    abstract protected function _GetQualifiedName(): array;

    /**
     * Return the command's options
     *
     * For example:
     *
     * ```php
     * protected function _GetOptions(): array
     * {
     *     return [
     *         CliOption::From([
     *             "long"        => "dest",
     *             "short"       => "d",
     *             "valueName"   => "DIR",
     *             "description" => "Sync files to DIR",
     *             "optionType"  => CliOptionType::VALUE,
     *             "required"    => true,
     *         ]),
     *     ];
     * }
     * ```
     *
     * @return array<int,CliOption|array>
     * @see TConstructible::From()
     */
    abstract protected function _GetOptions(): array;

    /**
     * Run the command and return an exit status
     *
     * @return int
     */
    abstract protected function _Run(...$params): int;

    /**
     * @var array<int,string>
     */
    private $QualifiedName;

    /**
     * @var array<int,CliOption>
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

    /**
     * Create an instance of the command and register it
     *
     * The following statements are equivalent:
     *
     * ```php
     * // 1.
     * MyCliCommand::Register();
     *
     * // 2.
     * Cli::RegisterCommand(new MyCliCommand());
     * ```
     *
     * But the only way to override a command's default `QualifiedName` is with
     * `CliCommand::Register()`:
     *
     * ```php
     * MyCliCommand::Register(["command", "subcommand", "my-cli-command"]);
     * ```
     *
     * @param array|null $qualifiedName If set, the qualified name returned by
     * the subclass will be ignored.
     */
    final public static function Register(array $qualifiedName = null)
    {
        $command = new static();

        if (!is_null($qualifiedName))
        {
            CliAssert::QualifiedNameIsValid($qualifiedName);
            $command->QualifiedName = $qualifiedName;
        }

        Cli::RegisterCommand($command);
    }

    final public function GetName(): string
    {
        return implode(" ", $this->GetQualifiedName());
    }

    final public function GetCommandName()
    {
        return Cli::GetProgramName() . " " . $this->GetName();
    }

    /**
     *
     * @return array<int,string>
     */
    final public function GetQualifiedName(): array
    {
        if (!$this->QualifiedName)
        {
            CliAssert::QualifiedNameIsValid($nameParts = $this->_GetQualifiedName());
            $this->QualifiedName = $nameParts;
        }

        return $this->QualifiedName;
    }

    /**
     *
     * @param CliOption|array $option
     * @param array|null $options
     * @param bool $hide
     */
    private function AddOption($option, array & $options = null, $hide = false)
    {
        if (is_array($option))
        {
            $option = CliOption::From($option);
        }

        if (!is_null($options))
        {
            $options[] = $option;
        }

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

    private function LoadOptions()
    {
        if (!is_null($this->Options))
        {
            return;
        }

        $_options = $this->_GetOptions();
        $options  = [];

        foreach ($_options as $option)
        {
            $this->AddOption($option, $options);
        }

        if (!array_key_exists("help", $this->OptionsByName))
        {
            $this->AddOption([
                "long"  => "help",
                "short" => array_key_exists("h", $this->OptionsByName) ? null : "h"
            ], $options, true);
        }

        $this->Options = $options;
    }

    /**
     *
     * @return array<int,CliOption>
     */
    final public function GetOptions(): array
    {
        $this->LoadOptions();

        return $this->Options;
    }

    final public function GetOptionByName(string $name)
    {
        $this->LoadOptions();

        return $this->OptionsByName[$name] ?? false;
    }

    final public function GetUsage(bool $line1 = false): string
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

        foreach ($this->GetOptions() as $option)
        {
            if (array_key_exists($option->Key, $this->HiddenOptionsByKey))
            {
                continue;
            }

            list ($short, $long, $line, $value) = [$option->Short, $option->Long, [], []];

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
                //     _-o, --option_[=value]
                //       Option description (default: _auto_)
                //       __Options:__ option1, option2, option3
                $options .= ("\n  _" . implode(", ", $line) . "_" . (array_pop($value) ?: "")
                    . ($option->Description ? "\n    " . $option->Description : "")
                    . ((!$option->IsFlag && $option->DefaultValue) ? ($option->Description ? " " : "\n    ")
                        . "(default: _" . implode(",", Convert::AnyToArray($option->DefaultValue)) . "_)" : "")
                    . ($option->AllowedValues ? "\n    __Options:__ " . implode(" ", $option->AllowedValues) : "")) . "\n";
            }
        }

        $synopsis = (($shortFlag ? " [-" . implode("", $shortFlag) . "]" : "")
            . ($longFlag ? " [--" . implode("] [--", $longFlag) . "]" : "")
            . ($optional ? " [" . implode("] [", $optional) . "]" : "")
            . ($required ? " " . implode(" ", $required) : ""));

        $name    = $this->GetCommandName();
        $options = trim($options, "\n");

        return $line1 ? $synopsis :
<<<EOF
___NAME___
  __{$name}__

___SYNOPSIS___
  __{$name}__{$synopsis}

___OPTIONS___
$options
EOF;
    }

    final protected function OptionError(string $message)
    {
        Console::Error($this->GetCommandName() . ": $message");
        $this->OptionErrors++;
    }

    private function LoadOptionValues()
    {
        if (!is_null($this->OptionValues))
        {
            return;
        }

        if (Cli::GetRunningCommand() !== $this)
        {
            throw new RuntimeException(static::class . " is not running");
        }

        $this->LoadOptions();
        $this->OptionErrors = 0;

        $args   = $GLOBALS["argv"];
        $merged = [];

        for ($i = Cli::GetFirstArgumentIndex(); $i < $GLOBALS["argc"]; $i++)
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
                    $this->OptionError("invalid argument '$arg'");

                    continue;
                }

                break;
            }

            $option = $this->OptionsByName[$name] ?? null;

            if (is_null($option))
            {
                $this->OptionError("unknown option '$name'");

                continue;
            }
            elseif ($option->IsFlag)
            {
                # Handle multiple short flags per argument, e.g. `cp -rv`
                if ($short && $value)
                {
                    $args[$i] = "-$value";
                    $i--;
                }

                $value = true;
            }
            elseif (!$option->IsValueRequired)
            {
                $value = $value ?: "";
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
                        $this->OptionError("{$option->DisplayName} value required");
                        $i--;
                    }
                }
            }

            $key = $option->Key;

            if (isset($merged[$key]))
            {
                $merged[$key] = array_merge(Convert::AnyToArray($merged[$key]), Convert::AnyToArray($value));
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
                $this->OptionError("{$option->DisplayName} cannot be used multiple times");
            }

            if (!is_null($option->AllowedValues) && !empty($invalid = array_diff(Convert::AnyToArray($value), $option->AllowedValues)))
            {
                $this->OptionError("invalid {$option->DisplayName} " . Convert::NumberToNoun(count($invalid), "value") . ": " . implode(", ", $invalid));
            }
        }

        foreach ($this->OptionsByKey as $option)
        {
            if ($option->IsRequired && !array_key_exists($option->Key, $merged))
            {
                if (!($GLOBALS["argc"] - Cli::GetFirstArgumentIndex() == 1 && $this->IsHelp))
                {
                    $this->OptionError("{$option->DisplayName} argument required");
                }
            }
            else
            {
                $value = $merged[$option->Key] ?? $option->DefaultValue;

                if ($option->IsFlag && $option->MultipleAllowed)
                {
                    $value = is_null($value) ? 0 : count(Convert::AnyToArray($value));
                }
                elseif ($option->MultipleAllowed)
                {
                    $value = is_null($value) ? [] : Convert::AnyToArray($value);
                }

                $option->SetValue($value);
            }
        }

        if ($this->OptionErrors)
        {
            throw new CliInvalidArgumentException();
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
     * @return string|array<int,string>|bool|int|null
     */
    final public function GetOptionValue(string $name)
    {
        if (!($option = $this->GetOptionByName($name)))
        {
            throw new UnexpectedValueException("No option with name '$name'");
        }

        $this->LoadOptionValues();

        return $option->Value;
    }

    final public function GetAllOptionValues()
    {
        $this->LoadOptionValues();

        $values = [];

        foreach ($this->Options as $option)
        {
            $name          = $option->Long ?: $option->Short;
            $values[$name] = $option->Value;
        }

        return $values;
    }

    final public function Run(): int
    {
        $this->LoadOptionValues();

        if ($this->IsHelp)
        {
            Console::PrintTtyOnly($this->GetUsage());

            return 0;
        }

        return $this->_Run(...array_slice($GLOBALS['argv'], $this->NextArgumentIndex));
    }
}

