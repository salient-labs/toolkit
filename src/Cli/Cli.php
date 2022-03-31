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

    public static function getProgramName(): string
    {
        return basename($GLOBALS["argv"][0]);
    }

    public static function getRunningCommand(): ?CliCommand
    {
        return self::$RunningCommand;
    }

    public static function getFirstArgumentIndex(): ?int
    {
        return self::$FirstArgumentIndex;
    }

    public static function getCommand(string $name): ?CliCommand
    {
        return self::$Commands[$name] ?? null;
    }

    /**
     *
     * @param string[] $name
     * @return array<string,array|CliCommand>|CliCommand|null
     */
    public static function getCommandTree(array $name = [])
    {
        $tree = self::$CommandTree;

        foreach ($name as $subcommand)
        {
            if (!is_array($tree))
            {
                return null;
            }

            $tree = $tree[$subcommand] ?? null;
        }

        return $tree;
    }

    /**
     *
     * @param CliCommand $command
     * @see CliCommand::register()
     */
    public static function registerCommand(CliCommand $command)
    {
        $name = $command->getName();

        if (!is_null(self::getCommandTree($name)))
        {
            throw new UnexpectedValueException("Command already registered at '" . implode(" ", $name) . "'");
        }

        $tree = & self::$CommandTree;
        $leaf = array_pop($name);

        foreach ($name as $subcommand)
        {
            if (!is_array($tree[$subcommand] ?? null))
            {
                $tree[$subcommand] = [];
            }

            $tree = & $tree[$subcommand];
        }

        $tree[$leaf] = $command;
        self::$Commands[$command->getCommandName()] = $command;
    }

    /**
     *
     * @param string $name
     * @param array|CliCommand $node
     * @return null|string
     */
    private static function getUsage(string $name, $node): ?string
    {
        if ($node instanceof CliCommand)
        {
            return $node->getUsage();
        }
        elseif (!is_array($node))
        {
            return null;
        }

        $name     = trim(self::getProgramName() . " $name");
        $synopses = [];

        foreach ($node as $childName => $childNode)
        {
            if ($childNode instanceof CliCommand)
            {
                $synopses[] = "_{$childName}_" . $childNode->getUsage(true);
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

    public static function runCommand(): int
    {
        Assert::sapiIsCli();

        $node = self::$CommandTree;
        $name = "";

        try
        {
            for ($i = 1; $i < $GLOBALS["argc"]; $i++)
            {
                $arg = $GLOBALS["argv"][$i];

                if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $arg))
                {
                    $node  = $node[$arg] ?? null;
                    $name .= ($name ? " " : "") . $arg;
                }
                elseif ($arg == "--help" && $i + 1 == $GLOBALS["argc"])
                {
                    Console::PrintTo(self::getUsage($name, $node), ...Console::GetOutputTargets());

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

                    return $node();
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

            if ($node && $usage = self::getUsage($name, $node))
            {
                Console::PrintTo($usage, ...Console::GetOutputTargets());
            }

            return 1;
        }
    }
}

