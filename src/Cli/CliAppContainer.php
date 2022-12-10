<?php declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Cli\Concept\CliCommand;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Container\AppContainer;
use Lkrms\Facade\Assert;
use Lkrms\Facade\Console;
use Lkrms\Facade\Sys;
use UnexpectedValueException;

/**
 * A service container for CLI applications
 *
 * Typically accessed via the {@see \Lkrms\Facade\Cli} facade.
 *
 */
final class CliAppContainer extends AppContainer
{
    /**
     * @var array<string,string>
     */
    private $Commands = [];

    /**
     * @var array<string,array|string>
     */
    private $CommandTree = [];

    /**
     * @var CliCommand|null
     */
    private $RunningCommand;

    public function __construct(string $basePath = null)
    {
        parent::__construct($basePath);

        Assert::sapiIsCli();
        Assert::argvIsRegistered();

        // Keep running, even if:
        // - the TTY disconnects
        // - `max_execution_time` is non-zero
        // - `memory_limit` is exceeded
        ini_set('ignore_user_abort', '1');
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '-1');
    }

    /**
     * Return the basename of the file used to run the script
     *
     * @return string
     */
    public function getProgramName(): string
    {
        return Sys::getProgramBasename();
    }

    /**
     * Return the CliCommand started from the command line
     *
     */
    public function getRunningCommand(): ?CliCommand
    {
        return $this->RunningCommand;
    }

    /**
     * Get a CliCommand instance from the given node in the command tree
     *
     * Returns `null` if no command is registered at the given node.
     *
     * @internal
     * @param string $name The name of the node as a space-delimited list of
     * subcommands.
     * @param array<string,array|string>|string|false|null $node The node as
     * returned by {@see CliAppContainer::getCommandTree()}.
     */
    protected function getNodeCommand(string $name, $node): ?CliCommand
    {
        if (is_string($node)) {
            if (!(($command = $this->get($node)) instanceof CliCommand)) {
                throw new UnexpectedValueException("Not a subclass of CliCommand: $node");
            }
            $command->setName($name ? explode(' ', $name) : []);

            return $command;
        }

        return null;
    }

    /**
     * Resolve an array of subcommand names to a node in the command tree
     *
     * Returns one of the following:
     * - `null` if nothing has been added to the tree at `$name`
     * - the name of the {@see CliCommand} class registered at `$name`
     * - an array that maps subcommands of `$name` to their respective nodes
     * - `false` if a {@see CliCommand} has been registered above `$name`, e.g.
     *   if `$name` is `["sync", "canvas", "from-sis"]` and a command has been
     *   registered at `["sync", "canvas"]`
     *
     * Nodes in the command tree are either subcommand arrays (branches) or
     * {@see CliCommand} class names (leaves).
     *
     * @internal
     * @param string[] $name
     * @return array<string,array|string>|string|false|null
     */
    protected function getNode(array $name = [])
    {
        $tree = $this->CommandTree;

        foreach ($name as $subcommand) {
            if (is_null($tree)) {
                return null;
            } elseif (!is_array($tree)) {
                return false;
            }

            $tree = $tree[$subcommand] ?? null;
        }

        return $tree ?: null;
    }

    /**
     * Register a CliCommand with the container
     *
     * For example, a PHP script called `sync-util` could register
     * `Acme\Canvas\SyncFromSis`, a `CliCommand` subclass, as follows:
     *
     * ```php
     * Cli::load()
     *     ->command(["sync", "canvas", "from-sis"], \Acme\Canvas\SyncFromSis::class)
     *     ->runAndExit();
     * ```
     *
     * Then, `Acme\Canvas\SyncFromSis` could be invoked with:
     *
     * ```shell
     * php sync-util sync canvas from-sis
     * ```
     *
     * @param string[] $name The command name as an array of subcommands. Valid
     * subcommands start with a letter, followed by any number of letters,
     * numbers, hyphens, or underscores.
     * @param string $id The {@see CliCommand} class to request from the
     * container when an instance is required.
     * @return $this
     * @throws UnexpectedValueException if `$name` is invalid or has already
     * been used.
     */
    public function command(array $name, string $id)
    {
        foreach ($name as $i => $subcommand) {
            Assert::patternMatches($subcommand, '/^[a-zA-Z][a-zA-Z0-9_-]*$/', "name[$i]");
        }

        if (!is_null($this->getNode($name))) {
            throw new UnexpectedValueException("Another command has been registered at '" . implode(' ', $name) . "'");
        }

        $tree   = &$this->CommandTree;
        $branch = $name;
        $leaf   = array_pop($branch);

        foreach ($branch as $subcommand) {
            if (!is_array($tree[$subcommand] ?? null)) {
                $tree[$subcommand] = [];
            }

            $tree = &$tree[$subcommand];
        }

        if (!is_null($leaf)) {
            $tree[$leaf] = $id;
        } else {
            $tree = $id;
        }

        $this->Commands[implode(' ', $name)] = $id;

        return $this;
    }

    /**
     * Generate a usage message for a command tree node
     *
     * @param string $name
     * @param array|string $node
     * @return string|null
     */
    private function getUsage(string $name, $node): ?string
    {
        if ($command = $this->getNodeCommand($name, $node)) {
            return $command->getUsage();
        } elseif (!is_array($node)) {
            return null;
        }

        $synopses = [];

        foreach ($node as $childName => $childNode) {
            if ($command = $this->getNodeCommand($name . ($name ? ' ' : '') . $childName, $childNode)) {
                $synopses[] = "_{$childName}_" . $command->getUsage(true);
            } elseif (is_array($childNode)) {
                $synopses[] = "_{$childName}_ <command>";
            }
        }

        $name     = trim($this->getProgramName() . " $name");
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
     * Process the command line
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
    public function run(): int
    {
        $args = array_slice($_SERVER['argv'], 1);
        $node = $this->CommandTree;
        $name = '';

        try {
            while (is_array($node)) {
                $arg = array_shift($args) ?: '';

                // 1. Descend into the command tree if $arg is a legal
                //    subcommand or unambiguous partial subcommand
                // 2. Push "--help" onto $args and continue if $arg is "help"
                // 3. Print usage info if $arg is "--help" and there are no
                //    further arguments
                // 4. Otherwise, fail
                if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $arg)) {
                    $nodes = array_filter(
                        $node,
                        function ($childName) use ($arg) {
                            return strpos($childName, $arg) === 0;
                        },
                        ARRAY_FILTER_USE_KEY
                    );

                    if (!$nodes) {
                        if ($arg == 'help') {
                            $args[] = '--help';
                            continue;
                        }
                    } elseif (count($nodes) == 1) {
                        $arg = key($nodes);
                    }

                    $node  = $node[$arg] ?? null;
                    $name .= ($name ? ' ' : '') . $arg;
                } elseif ($arg == '--help' && empty($args)) {
                    Console::out($this->getUsage($name, $node));

                    return 0;
                } else {
                    throw new CliArgumentsInvalidException('missing or incomplete command' . ($name ? " '$name'" : ''));
                }
            }

            if ($command = $this->getNodeCommand($name, $node)) {
                $this->RunningCommand = $command;

                return $command($args);
            } else {
                throw new CliArgumentsInvalidException("no command registered at '$name'");
            }
        } catch (CliArgumentsInvalidException $ex) {
            unset($ex);

            if ($node && $usage = $this->getUsage($name, $node)) {
                Console::out($usage);
            }

            return 1;
        }
    }

    /**
     * Process the command line and exit
     *
     * The value returned by {@see CliAppContainer::run()} is used as the exit
     * status.
     *
     * @return never
     */
    public function runAndExit()
    {
        exit($this->run());
    }
}
