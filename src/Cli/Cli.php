<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Assert;
use Lkrms\Console;
use Lkrms\Convert;
use Exception;

class Cli
{
    public const OPTION_TYPE_FLAG = 1 << 0;

    public const OPTION_TYPE_VALUE = 1 << 1;

    public const OPTION_TYPE_ONE_OF = 1 << 2;

    public const OPTION_REQUIRED = 1 << 3;

    public const OPTION_VALUE_NOT_REQUIRED = 1 << 4;

    public const OPTION_MULTIPLE_ALLOWED = 1 << 5;

    public const MASK_OPTION_TYPE = (1 << 3) - 1;

    private static $Options = [];

    private static $UniqueOptions = [];

    private static $UsageShortFlags = [];

    private static $UsageLongFlags = [];

    private static $UsageRequiredValues = [];

    private static $UsageOptionalValues = [];

    private static $UsageArguments = [];

    private static $GetOpt;

    private static $GetOptCalled = false;

    private static $NextArgumentIndex;

    public static function GetCommandName()
    {
        return basename($GLOBALS["argv"][0]);
    }

    public static function AddOption( ? string $long, ? string $short, ? string $valueName, ? string $description, int $flags = self::OPTION_TYPE_FLAG, array $allowedValues = null, $defaultValue = null)
    {
        self::AddCliOption( new CliOption($long, $short, $valueName, $description, $flags, $allowedValues, $defaultValue));
    }

    public static function AddCliOption(CliOption $option)
    {
        if (self::$GetOptCalled)
        {
            throw new Exception("Cannot add options after calling GetOpt");
        }

        $hasShort    = ! is_null($option->Short);
        $hasLong     = ! is_null($option->Long);
        $optionNames = [];

        if ($hasShort)
        {
            Assert::ExactStringLength($option->Short, 1, "option->Short");
            $optionNames[] = $option->Short;
        }

        if ($hasLong)
        {
            Assert::MinimumStringLength($option->Long, 2, "option->Long");
            $optionNames[] = $option->Long;
        }

        if ( ! count($optionNames))
        {
            throw new Exception("Option name missing");
        }

        if (count(array_intersect($optionNames, array_keys(self::$Options))))
        {
            throw new Exception("Option already defined");
        }

        self::$UniqueOptions[$option->Key] = $option;

        if ($hasShort)
        {
            self::$Options[$option->Short] = $option;
        }

        if ($hasLong)
        {
            self::$Options[$option->Long] = $option;
        }

        $usage = [];

        if ($option->IsFlag)
        {
            if ($hasShort)
            {
                $usage[] = "-" . $option->Short;

                // for abbreviated output
                self::$UsageShortFlags[] = $option->Short;
            }

            if ($hasLong)
            {
                if ( ! $hasShort)
                {
                    self::$UsageLongFlags[] = $option->Long;
                }

                $usage[] = "--" . $option->Long;
            }
        }
        else
        {
            $valueName   = $option->ValueName ?? "value";
            $shortSuffix = "";

            if ($hasShort)
            {
                $usage[]     = "-{$option->Short}";
                $shortSuffix = $option->IsValueRequired ? " $valueName" : "[$valueName]";
            }

            if ($hasLong)
            {
                $usage[] = "--{$option->Long}" . ($option->IsValueRequired ? " $valueName" : "[=$valueName]");
            }

            if ($option->IsRequired)
            {
                self::$UsageRequiredValues[] = $usage[0] . $shortSuffix;
            }
            else
            {
                self::$UsageOptionalValues[] = $usage[0] . $shortSuffix;
            }
        }

        self::$UsageArguments[] = [
            "argument"      => implode(", ", $usage),
            "description"   => $option->Description,
            "defaultValue"  => $option->IsFlag ? null : (is_string($option->DefaultValue) || is_null($option->DefaultValue) ? $option->DefaultValue : json_encode($option->DefaultValue)),
            "allowedValues" => $option->AllowedValues,
        ];
    }

    public static function Usage(int $status = 0) : void
    {
        $usage = "Usage: " . self::GetCommandName();

        if ( ! empty(self::$UsageShortFlags))
        {
            $usage .= " [-" . implode("", self::$UsageShortFlags) . "]";
        }

        if ( ! empty(self::$UsageLongFlags))
        {
            $usage .= " [--" . implode("] [--", self::$UsageLongFlags) . "]";
        }

        if ( ! empty(self::$UsageOptionalValues))
        {
            $usage .= " [" . implode("] [", self::$UsageOptionalValues) . "]";
        }

        if ( ! empty(self::$UsageRequiredValues))
        {
            $usage .= " " . implode(" ", self::$UsageRequiredValues);
        }

        foreach (self::$UsageArguments as $option)
        {
            $usage .= "\n\n  $option[argument]";
            $usage .= $option["description"] ? "\n    $option[description]" : "";
            $usage .= $option["allowedValues"] ? "\n    Available values: " . implode(" ", $option["allowedValues"]) : "";
            $usage .= $option["defaultValue"] ? "\n    Default: $option[defaultValue]" : "";
        }

        Console::Error($usage);
        exit ($status);
    }

    public static function GetOpt()
    {
        if ( ! self::$GetOptCalled)
        {
            self::$GetOptCalled = true;

            // build arguments to getopt
            $short = "";
            $long  = [];

            foreach (self::$UniqueOptions as $option)
            {
                $suffix = "";

                if ($option->IsValueRequired)
                {
                    $suffix = ":";
                }
                elseif ( ! $option->IsFlag)
                {
                    $suffix = "::";
                }

                if ($option->Short)
                {
                    $short .= $option->Short . $suffix;
                }

                if ($option->Long)
                {
                    $long[] = $option->Long . $suffix;
                }
            }

            $opt = getopt($short, $long, self::$NextArgumentIndex);

            if ($opt === false)
            {
                self::Usage(1);
            }

            $mergedOpt = [];
            $isHelp    = false;

            foreach ($opt as $o => $a)
            {
                $o = self::$Options[$o]->Key ?? null;

                if (is_null($o))
                {
                    throw new Exception("Unknown option returned by getopt");
                }

                if (isset($mergedOpt[$o]))
                {
                    $mergedOpt[$o] = array_merge(Convert::AnyToArray($mergedOpt[$o]), Convert::AnyToArray($a));
                }
                else
                {
                    $mergedOpt[$o] = $a;
                }
            }

            foreach ($mergedOpt as $o => $a)
            {
                $option = self::$UniqueOptions[$o];

                if ( ! $option->MultipleAllowed && is_array($a))
                {
                    Console::Error(self::GetCommandName() . ": {$option->DisplayName} cannot be used multiple times");
                    $opt = false;
                }

                if ( ! is_null($option->AllowedValues))
                {
                    $arr = Convert::AnyToArray($a);

                    foreach ($arr as $v)
                    {
                        if ( ! in_array($v, $option->AllowedValues))
                        {
                            Console::Error(self::GetCommandName() . ": invalid value for {$option->DisplayName} -- '$v'");
                            $opt = false;
                        }
                    }
                }
            }

            $isHelp = count(array_intersect(array_keys($mergedOpt), [
                "h|help",
                "|help"
            ]));

            foreach (self::$UniqueOptions as $o)
            {
                if ($o->IsRequired && ! isset($mergedOpt[$o->Key]))
                {
                    if ($GLOBALS["argc"] > 1 && ! $isHelp)
                    {
                        Console::Error(self::GetCommandName() . ": {$o->DisplayName} is required");
                    }

                    $opt = false;
                }
            }

            if ($opt === false)
            {
                self::Usage($isHelp ? 0 : 1);
            }

            self::$GetOpt = $mergedOpt;
        }

        return self::$GetOpt;
    }

    public static function GetOptionValue(string $optionName)
    {
        if ( ! isset(self::$Options[$optionName]))
        {
            throw new Exception("Option not defined");
        }

        $option = self::$Options[$optionName];
        $opt    = self::GetOpt();
        $val    = $opt[$option->Key] ?? $option->DefaultValue;

        if ($option->IsFlag)
        {
            // setting a flag should make its value true
            return is_array($val) ? count($val) : ! $val;
        }
        elseif ($option->MultipleAllowed)
        {
            return Convert::AnyToArray($val);
        }
        else
        {
            return $val;
        }
    }
}

