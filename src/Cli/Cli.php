<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Console\Console;
use Lkrms\Exception\InvalidCliArgumentException;
use Lkrms\Util\Assert;
use UnexpectedValueException;

/**
 * CLI app toolkit
 *
 */
abstract class Cli
{
    /**
     * @var array<string,CliCommand>
     */
    private static $Commands = [];

    /**
     * @var array<string,array|CliCommand>
     */
    private static $CommandTree = [];

    /**
     * @var CliCommand
     */
    private static $RunningCommand;

    /**
     * Return the name used to run the script
     *
     * @return string
     */
    public static function getProgramName(): string
    {
        return basename($GLOBALS["argv"][0]);
    }

    /**
     * Return the CliCommand started from the command line
     *
     * @return null|CliCommand
     */
    public static function getRunningCommand(): ?CliCommand
    {
        return self::$RunningCommand;
    }

    /**
     * Resolve an array of subcommand names to a node in the command tree
     *
     * Returns one of the following:
     * - `null` if nothing has been added to the tree at `$name`
     * - the {@see CliCommand} registered at `$name`
     * - an array that maps subcommands of `$name` to their respective nodes
     * - `false` if a {@see CliCommand} has been added to the tree above
     *   `$name`, e.g. if `$name` is `["sync", "canvas", "from-sis"]` and a
     *   command has been registered at `["sync", "canvas"]`
     *
     * Nodes in the command tree are either subcommand arrays (branches) or
     * {@see CliCommand} instances (leaves).
     *
     * @param string[] $name
     * @return array<string,array|CliCommand>|CliCommand|null|false
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
     * @internal
     * @param CliCommand $command
     * @see CliCommand::register()
     */
    public static function registerCommand(CliCommand $command)
    {
        if (!$command->isRegistrable() || $command->isRegistered())
        {
            throw new UnexpectedValueException("Instance cannot be registered (did you use " . get_class($command) . "::register()?)");
        }

        $name = $command->getName();

        if (!is_null(self::getCommandTree($name)))
        {
            throw new UnexpectedValueException("Another command has been registered at '" . implode(" ", $name) . "'");
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
     * Generate a usage message for a command tree node
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

    /**
     * Process command-line arguments and take appropriate action
     *
     * One of the following actions will be taken:
     * - if `--help` is the only remaining argument after processing any
     *   subcommand names, print a usage message and return `0`
     * - if subcommands resolve to a registered command, invoke it and return
     *   its exit status
     * - report an error, print a usage message, and return `1`
     *
     * @return int
     */
    public static function run(): int
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

                // 1. Descend into the command tree if $arg is a legal
                //    subcommand or unambiguous partial subcommand
                // 2. Push "--help" onto $args and continue if $arg is "help"
                // 3. Print usage info if $arg is "--help" and there are no
                //    further arguments
                // 4. Otherwise, fail
                if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $arg))
                {
                    $nodes = array_filter(
                        $node,
                        function ($childName) use ($arg)
                        {
                            return strpos($childName, $arg) === 0;
                        },
                        ARRAY_FILTER_USE_KEY
                    );

                    if (!$nodes)
                    {
                        if ($arg == "help")
                        {
                            $args[] = "--help";
                            continue;
                        }
                    }
                    elseif (count($nodes) == 1)
                    {
                        $arg = key($nodes);
                    }

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
                    throw new InvalidCliArgumentException("missing or incomplete command" . ($name ? " '$name'" : ""));
                }
            }

            if ($node instanceof CliCommand)
            {
                self::$RunningCommand = $node;

                return $node($args);
            }
            else
            {
                throw new InvalidCliArgumentException("no command registered at '$name'");
            }
        }
        catch (InvalidCliArgumentException $ex)
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
