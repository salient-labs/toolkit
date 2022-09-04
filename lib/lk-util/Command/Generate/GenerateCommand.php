<?php

declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\CliCommand;
use Lkrms\Facade\Convert;

/**
 * Base class for code generation commands
 *
 */
abstract class GenerateCommand extends CliCommand
{
    /**
     * @var string
     */
    protected $OutputClass;

    /**
     * @var string|null
     */
    protected $OutputNamespace;

    /**
     * @var string
     */
    protected $ClassPrefix;

    /**
     * Lowercase alias => qualified name
     *
     * @var array<string,string>
     */
    protected $AliasMap = [];

    /**
     * Lowercase qualified name => alias
     *
     * @var array<string,string>
     */
    protected $ImportMap = [];

    /**
     * Create an alias for a namespaced name and return an identifier to use in
     * generated code
     *
     * If an alias for `$fqcn` has already been assigned, the existing alias
     * will be returned. Similarly, if `$alias` has already been claimed, the
     * fully-qualified name will be returned.
     *
     * Otherwise, `use $fqcn[ as $alias];` will be queued for output and
     * `$alias` will be returned.
     *
     * @param null|string $alias If `null`, the basename of `$fqcn` will be
     * used.
     * @param bool $returnFqcn If `false`, return `null` instead of the FQCN if
     * `$alias` has already been claimed.
     */
    protected function getFqcnAlias(string $fqcn, ?string $alias = null, bool $returnFqcn = true): ?string
    {
        $fqcn  = ltrim($fqcn, "\\");
        $_fqcn = strtolower($fqcn);

        // If $fqcn already has an alias, use it
        if ($_alias = $this->ImportMap[$_fqcn] ?? null)
        {
            return $_alias;
        }

        if (is_null($alias))
        {
            $alias = Convert::classToBasename($fqcn);
        }

        $_alias = strtolower($alias);

        // Don't allow a conflict with the name of the generated class
        if (!strcasecmp($alias, $this->OutputClass) ||
            array_key_exists($_alias, $this->AliasMap))
        {
            return $returnFqcn ? $this->ClassPrefix . $fqcn : null;
        }

        $this->AliasMap[$_alias] = $fqcn;

        // Use $alias without importing $fqcn if:
        // - $fqcn is in the same namespace as the generated class; and
        // - the basename of $fqcn is the same as $alias
        if (!strcasecmp($fqcn, "{$this->OutputNamespace}\\{$alias}"))
        {
            return $alias;
        }

        // Otherwise, import $fqcn
        $this->ImportMap[$_fqcn] = $alias;
        return $alias;
    }

}
