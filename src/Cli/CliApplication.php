<?php declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Cli\Catalog\CliHelpSectionName;
use Lkrms\Cli\Contract\ICliApplication;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\CliCommand;
use Lkrms\Console\ConsoleFormatter as Formatter;
use Lkrms\Container\Application;
use Lkrms\Facade\Assert;
use Lkrms\Facade\Composer;
use Lkrms\Facade\Console;
use Lkrms\Facade\Sys;
use Lkrms\Utility\Convert;
use LogicException;

/**
 * A service container for CLI applications
 *
 */
class CliApplication extends Application implements ICliApplication
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
     * @param array<string,class-string<CliCommand>|mixed[]>|class-string<CliCommand>|false|null $node The node as returned by {@see CliApplication::getNode()}.
     */
    protected function getNodeCommand(string $name, $node): ?CliCommand
    {
        if (!is_string($node)) {
            return null;
        }

        if (!(($command = $this->get($node)) instanceof CliCommand)) {
            throw new LogicException("Not a subclass of CliCommand: $node");
        }
        $command->setName($name ? explode(' ', $name) : []);

        return $command;
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
            if ($tree === null) {
                return null;
            } elseif (!is_array($tree)) {
                return false;
            }

            $tree = $tree[$subcommand] ?? null;
        }

        return $tree ?: null;
    }

    /**
     * Register one, and only one, CliCommand for the lifetime of the container
     *
     * The command is registered with an empty name, placing it at the root of
     * the container's subcommand tree.
     *
     * @param class-string<CliCommand> $id The name of the class to instantiate.
     * @return $this
     * @throws LogicException if another command has already been registered.
     */
    public function oneCommand(string $id)
    {
        return $this->command([], $id);
    }

    /**
     * Register a CliCommand with the container
     *
     * For example, an executable PHP script called `sync-util` could register
     * `Acme\Canvas\Sync`, a {@see CliCommand} inheritor, as follows:
     *
     * ```php
     * (new CliApplication())
     *     ->command(['sync', 'canvas'], \Acme\Canvas\Sync::class)
     *     ->runAndExit();
     * ```
     *
     * Then, `Acme\Canvas\Sync` could be invoked with:
     *
     * ```shell
     * ./sync-util sync canvas
     * ```
     *
     * @param string[] $name The name of the command as an array of subcommands.
     *
     * Valid subcommands start with a letter, followed by any number of letters,
     * numbers, hyphens and underscores.
     * @param class-string<CliCommand> $id The name of the class to instantiate.
     * @return $this
     * @throws LogicException if `$name` is invalid or conflicts with a
     * registered command.
     */
    public function command(array $name, string $id)
    {
        foreach ($name as $i => $subcommand) {
            Assert::patternMatches($subcommand, '/^[a-zA-Z][a-zA-Z0-9_-]*$/', "name[$i]");
        }

        if ($this->getNode($name) !== null) {
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

        if ($leaf !== null) {
            $tree[$leaf] = $id;
        } else {
            $tree = $id;
        }

        return $this;
    }

    /**
     * Generate usage information or a help message for a command tree node
     *
     * @param array<string,class-string<CliCommand>|mixed[]>|class-string<CliCommand> $node
     */
    private function getUsage(string $name, $node, bool $terse = false): ?string
    {
        $width = $this->getHelpWidth($terse);
        $progName = $this->getProgramName();
        $fullName = trim("$progName $name");

        if ($command = $this->getNodeCommand($name, $node)) {
            if (!$terse) {
                return $command->getHelp(true, $width);
            }
            return Formatter::escapeTags($command->getSynopsis(false, $width)
                . "\n\nSee '"
                . ($name ? "$progName help $name" : "$progName --help")
                . "' for more information.");
        }

        if (!is_array($node)) {
            return null;
        }

        $synopses = [];
        foreach ($node as $childName => $childNode) {
            if ($command = $this->getNodeCommand(trim("$name $childName"), $childNode)) {
                if ($terse) {
                    $synopses[] = $command->getSynopsis(false, $width);
                } else {
                    $synopses[] = "__{$childName}__ - " . $command->description();
                }
            } elseif (is_array($childNode)) {
                if ($terse) {
                    $synopses[] = "$fullName $childName <command>";
                } else {
                    $synopses[] = "__{$childName}__";
                }
            }
        }
        $synopses = implode("\n", $synopses);

        if ($terse) {
            return Formatter::escapeTags("$synopses\n\nSee '"
                . (Convert::sparseToString(' ', ["$progName help", $name, '<command>']))
                . "' for more information.");
        }

        $sections = [
            CliHelpSectionName::NAME => $fullName,
            CliHelpSectionName::SYNOPSIS => '__' . $fullName . '__ <command>',
            'SUBCOMMANDS' => $synopses,
        ];

        return $this->buildHelp($sections);
    }

    /**
     * @inheritDoc
     */
    public function getHelpWidth(bool $terse = false): ?int
    {
        $width = Console::getWidth();

        if ($width === null) {
            return $width;
        }

        $width = max(76, $width);

        return $terse
            ? $width
            : $width - 4;
    }

    /**
     * @inheritDoc
     */
    public function buildHelp(array $sections): string
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
     * @inheritDoc
     */
    public function run(): int
    {
        $args = array_slice($_SERVER['argv'], 1);

        $lastNode = null;
        $lastName = null;
        $node = $this->CommandTree;
        $name = '';

        while (is_array($node)) {
            $arg = array_shift($args);

            // Print usage info if the last remaining $arg is "--help"
            if ($arg === '--help' && !$args) {
                $usage = $this->getUsage($name, $node);
                Console::stdout($usage);
                return 0;
            }

            // or version number if it's "--version"
            if ($arg === '--version' && !$args) {
                $appName = $this->getAppName();
                $version = Composer::getRootPackageVersion(true, true);
                Console::stdout("__{$appName}__ $version");
                return 0;
            }

            // - If $args was empty before this iteration, print terse usage
            //   info and exit without error
            // - If $arg cannot be a valid subcommand, print terse usage info
            //   and return a non-zero exit status
            if ($arg === null ||
                    !preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $arg)) {
                $usage = $this->getUsage($name, $node, true);
                Console::out($usage);
                return $arg === null
                    ? 0
                    : 1;
            }

            // Descend into the command tree if $arg is a registered subcommand
            // or an unambiguous abbreviation thereof
            $nodes = [];
            foreach ($node as $childName => $childNode) {
                if (strpos($childName, $arg) === 0) {
                    $nodes[$childName] = $childNode;
                }
            }
            switch (count($nodes)) {
                case 0:
                    // Push "--help" onto $args and continue if $arg is "help"
                    // or an abbreviation of "help"
                    if (strpos('help', $arg) === 0) {
                        $args[] = '--help';
                        continue 2;
                    }
                    break;
                case 1:
                    // Expand unambiguous subcommands to their full names
                    $arg = array_key_first($nodes);
                    break;
            }
            $lastNode = $node;
            $lastName = $name;
            $node = $node[$arg] ?? null;
            $name .= ($name === '' ? '' : ' ') . $arg;
        }

        $command = $this->getNodeCommand($name, $node);
        try {
            if (!$command) {
                throw new CliInvalidArgumentsException(
                    sprintf('no command registered: %s', $name)
                );
            }
            $this->RunningCommand = $command;
            return $command(...$args);
        } catch (CliInvalidArgumentsException $ex) {
            $ex->reportErrors();
            if (!$node) {
                $node = $lastNode;
                $name = $lastName;
            }
            if ($node &&
                    ($usage = $this->getUsage($name, $node, true)) !== null) {
                Console::out("\n{$usage}");
            }
            return 1;
        } finally {
            $this->RunningCommand = null;
        }
    }

    public function runAndExit()
    {
        exit ($this->run());
    }
}
