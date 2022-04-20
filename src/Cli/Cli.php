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
abstract class Cli
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

    public static function getProgramName(): string
    {
        return basename($GLOBALS["argv"][0]);
    }

    public static function getRunningCommand(): ?CliCommand
    {
        return self::$RunningCommand;
    }

    public static function getCommand(string $name): ?CliCommand
    {
        return self::$Commands[$name] ?? null;
    }

    /**
     *
     * @param string[] $name
     * @return array<string,array|CliCommand>|CliCommand|null|false `null` if
     * `$name` could be added to the tree, `false` if a command has been
     * registered above `$name`, otherwise the `CliCommand` or associative
     * subcommand array at `$name`.
     */
    public static function getCommandTree(array $name = [])
    {
        $tree = self::$CommandTree;

        foreach ($name as $subcommand)
        {
            if (is_null($tree))
            {
                return null;
            }
            elseif (!is_array($tree))
            {
                return false;
            }

            $tree = $tree[$subcommand] ?? null;
        }

        return $tree ?: null;
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

        if (!is_null($leaf))
        {
            $tree[$leaf] = $command;
        }
        else
        {
            $tree = $command;
        }

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

        return <<<EOF
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

        $args = array_slice($GLOBALS["argv"], 1);
        $node = self::$CommandTree;
        $name = "";

        try
        {
            while (is_array($node))
            {
                $arg = array_shift($args) ?: "";

                // Descend into the command tree if $arg is a legal subcommand,
                // print usage info if $arg is "--help" and there are no further
                // arguments, or fail
                if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $arg))
                {
                    $node  = $node[$arg] ?? null;
                    $name .= ($name ? " " : "") . $arg;
                }
                elseif ($arg == "--help" && empty($args))
                {
                    Console::printTo(self::getUsage($name, $node), ...Console::getOutputTargets());

                    return 0;
                }
                else
                {
                    throw new CliInvalidArgumentException("missing or incomplete command" . ($name ? " '$name'" : ""));
                }
            }

            if ($node instanceof CliCommand)
            {
                self::$RunningCommand = $node;

                return $node($args);
            }
            else
            {
                throw new CliInvalidArgumentException("no command registered at '$name'");
            }
        }
        catch (CliInvalidArgumentException $ex)
        {
            unset($ex);

            if ($node && $usage = self::getUsage($name, $node))
            {
                Console::printTo($usage, ...Console::getOutputTargets());
            }

            return 1;
        }
    }
}

