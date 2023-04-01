<?php declare(strict_types=1);

/**
 * @package Lkrms\LkUtil
 */

namespace Lkrms\LkUtil\Command\Generate\Concept;

use Lkrms\Facade\Composer;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\File;
use Lkrms\Facade\Reflect;
use Lkrms\LkUtil\Command\Concept\Command;
use ReflectionParameter;
use ReflectionType;

/**
 * Base class for code generation commands
 *
 */
abstract class GenerateCommand extends Command
{
    protected const VISIBILITY_PUBLIC    = 'public';
    protected const VISIBILITY_PROTECTED = 'protected';
    protected const VISIBILITY_PRIVATE   = 'private';

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
     * Resolve PHPDoc templates to concrete types if possible
     *
     * @param array<string,array{type:string|null}> $templates
     */
    protected function resolveTemplates(string $type, array $templates): string
    {
        $seen = [];
        while (($_type = $templates[$type]['type'] ?? null) && !($seen[$_type] ?? null)) {
            $seen[$_type] = true;
            $type         = $_type;
        }

        return $type;
    }

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
     * @param string|null $alias If `null`, the basename of `$fqcn` will be
     * used.
     * @param bool $returnFqcn If `false`, return `null` instead of the FQCN if
     * `$alias` has already been claimed.
     */
    protected function getFqcnAlias(string $fqcn, ?string $alias = null, bool $returnFqcn = true): ?string
    {
        $fqcn  = ltrim($fqcn, '\\');
        $_fqcn = strtolower($fqcn);

        // If $fqcn has already been imported, use its alias
        if ($lastAlias = $this->ImportMap[$_fqcn] ?? null) {
            return $lastAlias;
        }

        $alias  = is_null($alias) ? Convert::classToBasename($fqcn) : $alias;
        $_alias = strtolower($alias);

        // Use $alias if it already maps to $fqcn
        if (($aliasFqcn = $this->AliasMap[$_alias] ?? null) &&
                !strcasecmp($aliasFqcn, $fqcn)) {
            return $alias;
        }

        // Use the canonical basename of the generated class
        if (!strcasecmp($fqcn, "{$this->OutputNamespace}\\{$this->OutputClass}")) {
            return $this->OutputClass;
        }

        // Don't allow a conflict with the name of the generated class
        if (!strcasecmp($alias, $this->OutputClass) ||
                array_key_exists($_alias, $this->AliasMap)) {
            return $returnFqcn ? $this->ClassPrefix . $fqcn : null;
        }

        $this->AliasMap[$_alias] = $fqcn;

        // Use $alias without importing $fqcn if:
        // - $fqcn is in the same namespace as the generated class; and
        // - the basename of $fqcn is the same as $alias
        if (!strcasecmp($fqcn, "{$this->OutputNamespace}\\{$alias}")) {
            return $alias;
        }

        // Otherwise, import $fqcn
        $this->ImportMap[$_fqcn] = $alias;

        return $alias;
    }

    /**
     * Get a list of `use $fqcn[ as $alias];` statements
     *
     * @return string[]
     * @see GenerateCommand::getFqcnAlias()
     */
    protected function getImports(): array
    {
        $imports = [];
        foreach ($this->ImportMap as $alias) {
            $import = $this->AliasMap[strtolower($alias)];
            if (!strcasecmp($alias, Convert::classToBasename($import))) {
                $imports[] = "use $import;";
                continue;
            }
            $imports[] = "use $import as $alias;";
        }
        sort($imports);

        return $imports;
    }

    /**
     * Generate a `protected static function` that returns a fixed value
     *
     * @return string[]
     */
    protected function getStaticGetter(string $name, string $rawValue, string $rawParams = '', string $returnType = 'string', int $tabs = 1, string $tab = '    '): array
    {
        $lines = [
            '/**',
            ' * @internal',
            ' */',
            "protected static function {$name}({$rawParams}): {$returnType}",
            '{',
            "{$tab}return {$rawValue};",
            '}'
        ];

        return array_map(fn($line) => str_repeat($tab, $tabs) . $line, $lines);
    }

    /**
     * Generate a method
     *
     * @param string[] $code
     * @param array<ReflectionParameter|string> $params
     * @param ReflectionType|string $returnType
     * @return string[]
     */
    protected function getMethod(string $name, array $code, array $params = [], $returnType = null, ?string $docBlock = null, bool $static = true, string $visibility = GenerateCommand::VISIBILITY_PUBLIC, int $tabs = 1, string $tab = '    '): array
    {
        $callback  = fn(string $name): ?string => $this->getFqcnAlias($name, null, false);
        $rawParams = [];
        foreach ($params as $param) {
            if ($param instanceof ReflectionParameter) {
                $param = Reflect::getParameterDeclaration($param, $this->ClassPrefix, $callback);
            }
            $rawParams[] = $param;
        }
        $rawParams = implode(', ', $rawParams);
        if ($returnType instanceof ReflectionType) {
            $returnType = Reflect::getTypeDeclaration($returnType, $this->ClassPrefix, $callback);
        }

        $modifiers = [$visibility];
        if ($static) {
            $modifiers[] = 'static';
        }
        $modifiers = implode(' ', $modifiers);

        $lines = [
            ...($docBlock ? explode(PHP_EOL, $docBlock) : []),
            $modifiers . " function {$name}({$rawParams})" . ($returnType ? ": {$returnType}" : ''),
            '{',
            ...array_map(fn($line) => "{$tab}$line", $code),
            '}'
        ];

        return array_map(fn($line) => str_repeat($tab, $tabs) . $line, $lines);
    }

    /**
     *
     * @param string[] $lines
     */
    protected function handleOutput(string $class, string $namespace, array $lines): void
    {
        $output = implode(PHP_EOL, $lines) . PHP_EOL;

        $verb = 'Creating';

        if ($this->getOptionValue('stdout')) {
            $file = 'php://stdout';
            $verb = null;
        } else {
            $file = "$class.php";

            if ($dir = Composer::getNamespacePath($namespace)) {
                File::maybeCreateDirectory($dir);
                $file = $dir . DIRECTORY_SEPARATOR . $file;
            }
            if (file_exists($file)) {
                if (rtrim(file_get_contents($file)) == rtrim($output)) {
                    Console::log('Unchanged:', $file);

                    return;
                }
                if (!$this->getOptionValue('force')) {
                    Console::warn('File already exists:', $file);
                    $file = substr($file, 0, -4) . '.generated.php';
                }
                if (file_exists($file)) {
                    $verb = 'Replacing';
                }
            }
        }

        if ($verb) {
            Console::info($verb, $file);
        }

        file_put_contents($file, $output);
    }

    protected function cleanPhpDocTag(string $tag): string
    {
        return preg_replace('/^(@(?:param|return|var|property-(?:read|write)|property)\h+)\?([^\s&|]+)(\h+\$)/', '$1$2|null$3', $tag);
    }
}
