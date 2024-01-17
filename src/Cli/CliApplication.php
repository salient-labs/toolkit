<?php declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Cli\Catalog\CliHelpSectionName;
use Lkrms\Cli\Catalog\CliHelpTarget;
use Lkrms\Cli\Contract\ICliApplication;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\Support\CliHelpStyle;
use Lkrms\Console\Support\ConsoleManPageFormat;
use Lkrms\Console\Support\ConsoleMarkdownFormat;
use Lkrms\Console\ConsoleFormatter as Formatter;
use Lkrms\Container\Application;
use Lkrms\Facade\Console;
use Lkrms\Utility\Catalog\EnvFlag;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Assert;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Json;
use Lkrms\Utility\Package;
use Lkrms\Utility\Pcre;
use Lkrms\Utility\Str;
use Lkrms\Utility\Sys;
use LogicException;

/**
 * A service container for CLI applications
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

    /**
     * @var int
     */
    private $LastExitStatus = 0;

    public function __construct(
        ?string $basePath = null,
        ?string $appName = null,
        int $envFlags = EnvFlag::ALL
    ) {
        parent::__construct($basePath, $appName, $envFlags);

        Assert::runningOnCli();
        Assert::argvIsDeclared();

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
     * @inheritDoc
     */
    public function getRunningCommand(): ?CliCommand
    {
        return $this->RunningCommand;
    }

    /**
     * @inheritDoc
     */
    public function getLastExitStatus(): int
    {
        return $this->LastExitStatus;
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
            Assert::isMatch($subcommand, '/^[a-zA-Z][a-zA-Z0-9_-]*$/', "\$name[$i]");
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
     * Get a help message for a command tree node
     *
     * @param array<string,class-string<CliCommand>|mixed[]>|class-string<CliCommand> $node
     */
    private function getHelp(string $name, $node, ?CliHelpStyle $style = null): ?string
    {
        $style ??= new CliHelpStyle(CliHelpTarget::TTY);

        $command = $this->getNodeCommand($name, $node);
        if ($command) {
            return $style->buildHelp($command->getHelp($style));
        }

        if (!is_array($node)) {
            return null;
        }

        $progName = $this->getProgramName();
        $fullName = trim("$progName $name");
        $synopses = [];
        foreach ($node as $childName => $childNode) {
            $command = $this->getNodeCommand(trim("$name $childName"), $childNode);
            if ($command) {
                $synopses[] = '__' . $childName . '__ - ' . Formatter::escapeTags($command->description());
            } elseif (is_array($childNode)) {
                $synopses[] = '__' . $childName . '__';
            }
        }

        return $style->buildHelp([
            CliHelpSectionName::NAME => $fullName,
            CliHelpSectionName::SYNOPSIS => '__' . $fullName . '__ <command>',
            'SUBCOMMANDS' => implode("\n", $synopses),
        ]);
    }

    /**
     * Get usage information for a command tree node
     *
     * @param array<string,class-string<CliCommand>|mixed[]>|class-string<CliCommand> $node
     */
    private function getUsage(string $name, $node): ?string
    {
        $style = new CliHelpStyle(CliHelpTarget::INTERNAL, CliHelpStyle::getConsoleWidth());

        $command = $this->getNodeCommand($name, $node);
        $progName = $this->getProgramName();

        if ($command) {
            return Formatter::escapeTags($command->getSynopsis($style)
                . "\n\nSee '"
                . ($name === '' ? "$progName --help" : "$progName help $name")
                . "' for more information.");
        }

        if (!is_array($node)) {
            return null;
        }

        $style = $style->withCollapseSynopsis();
        $fullName = trim("$progName $name");
        $synopses = [];
        foreach ($node as $childName => $childNode) {
            $command = $this->getNodeCommand(trim("$name $childName"), $childNode);
            if ($command) {
                $synopses[] = $command->getSynopsis($style);
            } elseif (is_array($childNode)) {
                $synopses[] = "$fullName $childName <command>";
            }
        }

        return Formatter::escapeTags(implode("\n", $synopses)
            . "\n\nSee '"
            . Arr::implode(' ', ["$progName help", $name, '<command>'])
            . "' for more information.");
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        $this->LastExitStatus = 0;

        $args = array_slice($_SERVER['argv'], 1);

        $lastNode = null;
        $lastName = null;
        $node = $this->CommandTree;
        $name = '';

        while (is_array($node)) {
            $arg = array_shift($args);

            // Print usage info if the last remaining $arg is "--help"
            if ($arg === '--help' && !$args) {
                $usage = $this->getHelp($name, $node);
                Console::stdout($usage);
                return $this;
            }

            // or version number if it's "--version"
            if ($arg === '--version' && !$args) {
                $appName = $this->getAppName();
                $version = Package::version(true, true);
                Console::stdout('__' . $appName . "__ $version");
                return $this;
            }

            // - If $args was empty before this iteration, print terse usage
            //   info and exit without error
            // - If $arg cannot be a valid subcommand, print terse usage info
            //   and return a non-zero exit status
            if (
                $arg === null ||
                !Pcre::match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $arg)
            ) {
                $usage = $this->getUsage($name, $node);
                Console::out($usage);
                $this->LastExitStatus =
                    $arg === null
                        ? 0
                        : 1;
                return $this;
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

        if ($args && $args[0] === '_md') {
            array_shift($args);
            $this->generateHelp($name, $node, CliHelpTarget::MARKDOWN, ...$args);
            return $this;
        }

        if ($args && $args[0] === '_man') {
            array_shift($args);
            $this->generateHelp($name, $node, CliHelpTarget::MAN_PAGE, ...$args);
            return $this;
        }

        $command = $this->getNodeCommand($name, $node);

        try {
            if (!$command) {
                throw new CliInvalidArgumentsException(
                    sprintf('no command registered: %s', $name)
                );
            }

            if ($args && $args[0] === '_json_schema') {
                array_shift($args);
                $schema = $command->getJsonSchema($args[0] ?? trim($this->getProgramName() . " $name") . ' options');
                printf("%s\n", Json::prettyPrint($schema));
                return $this;
            }

            $this->RunningCommand = $command;
            $this->LastExitStatus = $command(...$args);
            return $this;
        } catch (CliInvalidArgumentsException $ex) {
            $ex->reportErrors();
            if (!$node) {
                $node = $lastNode;
                $name = $lastName;
            }
            if (
                $node &&
                ($usage = $this->getUsage($name, $node)) !== null
            ) {
                Console::out("\n" . $usage);
            }
            $this->LastExitStatus = 1;
            return $this;
        } finally {
            $this->RunningCommand = null;
        }
    }

    /**
     * @inheritDoc
     */
    public function exit()
    {
        exit ($this->LastExitStatus);
    }

    /**
     * @inheritDoc
     */
    public function runAndExit()
    {
        $this->run()->exit();
    }

    /**
     * @param array<string,class-string<CliCommand>|mixed[]>|class-string<CliCommand> $node
     * @param int&CliHelpTarget::* $target
     */
    private function generateHelp(string $name, $node, int $target, string ...$args): void
    {
        $collapseSynopsis = null;

        switch ($target) {
            case CliHelpTarget::MARKDOWN:
                $formats = ConsoleMarkdownFormat::getTagFormats();
                $collapseSynopsis = Convert::toBool($args[0] ?? null);
                break;

            case CliHelpTarget::MAN_PAGE:
                $formats = ConsoleManPageFormat::getTagFormats();
                $progName = $this->getProgramName();
                printf(
                    "%% %s(%d) %s | %s\n\n",
                    Str::upper(str_replace(' ', '-', trim("$progName $name"))),
                    (int) ($args[0] ?? '1'),
                    $args[1] ?? Package::version(),
                    $args[2] ?? (($name === '' ? $progName : Package::name()) . ' Documentation'),
                );
                break;

            default:
                throw new LogicException(sprintf('Invalid CliHelpTarget: %d', $target));
        }

        $formatter = new Formatter($formats, null, fn(): int => 80);
        $style = new CliHelpStyle($target, 80, $formatter);

        if ($collapseSynopsis !== null) {
            $style = $style->withCollapseSynopsis($collapseSynopsis);
        }

        $usage = $this->getHelp($name, $node, $style);
        $usage = $formatter->formatTags($usage);
        printf("%s\n", str_replace('\ ', ' ', $usage));
    }
}
