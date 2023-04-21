<?php declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Cli\Catalog\CliUsageSectionName;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Container\AppContainer;
use Lkrms\Facade\Assert;
use Lkrms\Facade\Composer;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Sys;
use LogicException;

/**
 * A service container for CLI applications
 *
 * Typically accessed via the {@see \Lkrms\Facade\Cli} facade.
 *
 */
final class CliApplication extends AppContainer
{
    /**
     * @var array<string,class-string<CliCommand>|mixed[]>
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
        ignore_user_abort(true);
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        // Exit cleanly when interrupted
        Sys::handleExitSignals();
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
     * @param array<string,class-string<CliCommand>|mixed[]>|class-string<CliCommand>|false|null $node The node as returned by {@see CliAppContainer::getNode()}.
     */
    protected function getNodeCommand(string $name, $node): ?CliCommand
    {
        if (is_string($node)) {
            if (!(($command = $this->get($node)) instanceof CliCommand)) {
                throw new LogicException("Not a subclass of CliCommand: $node");
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
     * @return array<string,class-string<CliCommand>|mixed[]>|class-string<CliCommand>|false|null
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
     * @param class-string<CliCommand> $id The {@see CliCommand} class to
     * request from the container when an instance is required.
     * @return $this
     * @throws LogicException if `$name` is invalid or has already been used.
     */
    public function command(array $name, string $id)
    {
        foreach ($name as $i => $subcommand) {
            Assert::patternMatches($subcommand, '/^[a-zA-Z][a-zA-Z0-9_-]*$/', "name[$i]");
        }

        if (!is_null($this->getNode($name))) {
            throw new LogicException("Another command has been registered at '" . implode(' ', $name) . "'");
        }

        $tree = &$this->CommandTree;
        $branch = $name;
        $leaf = array_pop($branch);

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

        return $this;
    }

    /**
     * Generate a usage message for a command tree node
     *
     * @param array<string,class-string<CliCommand>|mixed[]>|class-string<CliCommand> $node
     */
    private function getUsage(string $name, $node, bool $terse = false): ?string
    {
        $progName = $this->getProgramName();
        $fullName = trim("$progName $name");
        if ($command = $this->getNodeCommand($name, $node)) {
            return $terse
                ? $command->getSynopsis(false)
                    . "\n\nSee '"
                    . ($name ? "$progName help $name" : "$progName --help")
                    . "' for more information."
                : $command->getUsage();
        } elseif (!is_array($node)) {
            return null;
        }

        $synopses = [];
        foreach ($node as $childName => $childNode) {
            if ($command = $this->getNodeCommand($name . ($name ? ' ' : '') . $childName, $childNode)) {
                $synopses[] = $terse ? $command->getSynopsis(false) : $command->getSubcommandSynopsis();
            } elseif (is_array($childNode)) {
                $synopses[] = ($terse ? "$fullName $childName" : "__{$childName}__") . ' <command>';
            }
        }
        $synopses = implode("\n", $synopses);

        if ($terse) {
            return "$synopses\n\nSee '"
                . (Convert::sparseToString(' ', ["$progName help", $name, '<command>']))
                . "' for more information.";
        }

        $sections = [
            CliUsageSectionName::NAME => $fullName,
            CliUsageSectionName::SYNOPSIS => '__' . $fullName . '__ <command>',
            'SUBCOMMANDS' => $synopses,
        ];

        return $this->buildUsageSections($sections);
    }

    /**
     * @internal
     * @param array<string,string> $sections
     */
    public function buildUsageSections(array $sections): string
    {
        $usage = '';
        foreach ($sections as $heading => $content) {
            if (!trim($content)) {
                continue;
            }
            $content = str_replace("\n", "\n    ", rtrim($content));
            $usage .= <<<EOF
## {$heading}
    {$content}


EOF;
        }

        return rtrim($usage);
    }

    /**
     * Process command-line arguments passed to the script
     *
     * One of the following actions will be taken:
     * - if subcommand arguments resolve to a registered command, invoke it and
     *   return its exit status
     * - if `--help` is the only remaining argument after processing subcommand
     *   arguments, print a usage message and return `0`
     * - if `--version` is the only remaining argument, print the application's
     *   name and version number and return `0`
     * - if, after processing subcommand arguments, there are no further
     *   arguments but there are further subcommands, print a one-line synopsis
     *   of each registered subcommand and return `0`
     * - report an error, print a one-line synopsis of each registered
     *   subcommand and return `1`
     *
     * @return int
     */
    public function run(): int
    {
        $args = array_slice($_SERVER['argv'], 1);
        $node = $this->CommandTree;
        $name = '';

        $lastNode = null;
        $lastName = null;
        try {
            while (is_array($node)) {
                $arg = array_shift($args);

                // 1. Descend into the command tree if $arg is a legal
                //    subcommand or unambiguous partial subcommand
                // 2. Push "--help" onto $args and continue if $arg is "help"
                // 3. If there are no further arguments, print usage info if
                //    $arg is "--help" or version number if $arg is "--version"
                // 4. Otherwise, fail
                if ($arg && preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $arg)) {
                    $nodes = array_filter(
                        $node,
                        fn(string $childName): bool => strpos($childName, $arg) === 0,
                        ARRAY_FILTER_USE_KEY
                    );
                    switch (count($nodes)) {
                        case 0:
                            if ($arg === 'help') {
                                $args[] = '--help';
                                continue 2;
                            }
                            break;
                        case 1:
                            $arg = array_keys($nodes)[0];
                            break;
                    }
                    $lastNode = $node;
                    $lastName = $name;
                    $node = $node[$arg] ?? null;
                    $name .= ($name ? ' ' : '') . $arg;
                } elseif ($arg === '--help' && empty($args)) {
                    Console::out($this->getUsage($name, $node));

                    return 0;
                } elseif ($arg === '--version' && empty($args)) {
                    $appName = $this->getAppName();
                    $version = Composer::getRootPackageVersion(true, true);
                    Console::out("__{$appName}__ $version");

                    return 0;
                } else {
                    Console::out($this->getUsage($name, $node, true));

                    // Exit without error unless there are unconsumed arguments
                    return is_null($arg) ? 0 : 1;
                }
            }

            if ($command = $this->getNodeCommand($name, $node)) {
                $this->RunningCommand = $command;

                $result = $command($args);

                $this->RunningCommand = null;

                return $result;
            } else {
                throw new CliInvalidArgumentsException("no command registered at '$name'");
            }
        } catch (CliInvalidArgumentsException $ex) {
            $this->RunningCommand = null;
            $ex->reportErrors();
            if (($node && ($usage = $this->getUsage($name, $node, true))) ||
                    ($lastNode && ($usage = $this->getUsage($lastName, $lastNode, true)))) {
                Console::out($usage);
            }

            return 1;
        }
    }

    /**
     * Exit after processing command-line arguments passed to the script
     *
     * The value returned by {@see CliAppContainer::run()} is used as the exit
     * status.
     *
     * @return never
     */
    public function runAndExit()
    {
        exit ($this->run());
    }
}
