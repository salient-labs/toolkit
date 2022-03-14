<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Assert;
use Lkrms\Console\Console;
use UnexpectedValueException;

/**
 * CLI app toolkit
 *
 * @package Lkrms
 */
class Cli
{
    /**
     * @var array<string,CliCommand>;
     */
    private static $Commands = [];

    /**
     * @var array<string,array<string,array|CliCommand>|CliCommand>
     */
    private static $CommandTree = [];

    /**
     * @var CliCommand
     */
    private static $RunningCommand;

    /**
     * @var int
     */
    private static $FirstArgumentIndex;

    public static function GetProgramName(): string
    {
        return basename($GLOBALS["argv"][0]);
    }

    public static function GetRunningCommand(): ?CliCommand
    {
        return self::$RunningCommand;
    }

    public static function GetFirstArgumentIndex(): ?int
    {
        return self::$FirstArgumentIndex;
    }

    public static function GetCommand(string $name): ?CliCommand
    {
        return self::$Commands[$name] ?? null;
    }

    /**
     *
     * @param string[] $nameParts
     * @return array<string,array|CliCommand>|CliCommand|null
     */
    public static function GetCommandTree(array $nameParts = [])
    {
        $tree = self::$CommandTree;

        foreach ($nameParts as $part)
        {
            if (!is_array($tree))
            {
                return null;
            }

            $tree = $tree[$part] ?? null;
        }

        return $tree;
    }

    /**
     *
     * @param CliCommand $command
     * @see CliCommand::Register()
     */
    public static function RegisterCommand(CliCommand $command)
    {
        $nameParts = $command->GetQualifiedName();

        if (!is_null(self::GetCommandTree($nameParts)))
        {
            throw new UnexpectedValueException("Command already registered at '" . implode(" ", $nameParts) . "'");
        }

        $tree = & self::$CommandTree;
        $name = array_pop($nameParts);

        foreach ($nameParts as $part)
        {
            if (!is_array($tree[$part] ?? null))
            {
                $tree[$part] = [];
            }

            $tree = & $tree[$part];
        }

        $tree[$name] = $command;
        self::$Commands[$command->GetName()] = $command;
    }

    /**
     *
     * @param string $name
     * @param array|CliCommand $node
     * @return string
     */
    private static function GetUsage(string $name, $node): string
    {
        if ($node instanceof CliCommand)
        {
            return $node->GetUsage();
        }
        elseif (is_array($node))
        {
            $name     = trim(self::GetProgramName() . " $name");
            $synopses = [];

            foreach ($node as $childName => $childNode)
            {
                if ($childNode instanceof CliCommand)
                {
                    $synopses[] = "_{$childName}_" . $childNode->GetUsage(true);
                }
                elseif (is_array($childNode))
                {
                    $synopses[] = "_{$childName}_ <command>";
                }
            }

            $synopses = implode("\n  ", $synopses);

            return
<<<EOF
___NAME___
  __{$name}__

___SYNOPSIS___
  __{$name}__ <command>

___SUBCOMMANDS___
  $synopses
EOF;
        }
    }

    public static function RunCommand(): int
    {
        Assert::SapiIsCli();

        $node = self::$CommandTree;
        $name = "";

        try
        {
            for ($i = 1; $i < $GLOBALS["argc"]; $i++)
            {
                $part = $GLOBALS["argv"][$i];

                if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $part))
                {
                    $node  = $node[$part] ?? null;
                    $name .= ($name ? " " : "") . $part;
                }
                elseif ($part == "--help" && $i + 1 == $GLOBALS["argc"])
                {
                    Console::PrintTtyOnly(self::GetUsage($name, $node));

                    return 0;
                }
                else
                {
                    break;
                }

                if ($node instanceof CliCommand)
                {
                    self::$RunningCommand     = $node;
                    self::$FirstArgumentIndex = $i + 1;

                    return $node->Run();
                }
                elseif (!is_array($node))
                {
                    throw new CliInvalidArgumentException("no command registered at '$name'");
                }
            }

            throw new CliInvalidArgumentException("missing or incomplete command" . ($name ? " '$name'" : ""));
        }
        catch (CliInvalidArgumentException $ex)
        {
            unset($ex);

            if ($node && $usage = self::GetUsage($name, $node))
            {
                Console::PrintTtyOnly($usage);
            }

            return 1;
        }
    }
}

