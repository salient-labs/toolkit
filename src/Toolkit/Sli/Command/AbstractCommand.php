<?php declare(strict_types=1);

namespace Salient\Sli\Command;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliCommand;
use Salient\Cli\CliUtil;
use Salient\Sli\EnvVar;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Get;
use Salient\Utility\Test;

abstract class AbstractCommand extends CliCommand
{
    /**
     * Normalise a user-supplied class name, optionally assigning its base name
     * and/or namespace to variables passed by reference
     *
     * @param-out string $class
     * @param-out string $namespace
     * @return class-string|''
     */
    protected function getFqcnOptionValue(
        string $valueName,
        string $value,
        ?string $namespaceEnvVar = null,
        ?string &$class = null,
        ?string &$namespace = null
    ): string {
        if ($value === '') {
            $class = '';
            $namespace = '';
            return '';
        }

        $ns = null;
        if ($namespaceEnvVar !== null) {
            $ns = Env::get($namespaceEnvVar, null);
        }
        $ns ??= Env::get(EnvVar::NS_DEFAULT, null);

        if ($ns !== null && strpos($value, '\\') === false) {
            $fqcn = trim($ns, '\\') . "\\$value";
        } else {
            $fqcn = ltrim($value, '\\');
        }

        if (!Test::isFqcn($fqcn)) {
            throw new CliInvalidArgumentsException(
                sprintf('invalid %s: %s', $valueName, $value),
            );
        }

        $class = Get::basename($fqcn);
        $namespace = Get::namespace($fqcn);
        return $fqcn;
    }

    /**
     * Normalise a mandatory user-supplied class name, optionally assigning its
     * base name and/or namespace to variables passed by reference
     *
     * @param-out string $class
     * @param-out string $namespace
     * @return class-string
     */
    protected function requireFqcnOptionValue(
        string $valueName,
        string $value,
        ?string $namespaceEnvVar = null,
        ?string &$class = null,
        ?string &$namespace = null
    ): string {
        $fqcn = $this->getFqcnOptionValue($valueName, $value, $namespaceEnvVar, $class, $namespace);
        if ($fqcn === '') {
            throw new CliInvalidArgumentsException(
                sprintf('invalid %s: %s', $valueName, $value),
            );
        }
        return $fqcn;
    }

    /**
     * Normalise an array of user-supplied class names
     *
     * @param string[] $values
     * @return array<class-string>
     */
    protected function requireFqcnOptionValues(
        string $valueName,
        array $values,
        ?string $namespaceEnvVar = null
    ): array {
        foreach ($values as $value) {
            $fqcn[] = $this->requireFqcnOptionValue($valueName, $value, $namespaceEnvVar);
        }
        return $fqcn ?? [];
    }

    /**
     * Normalise a user-supplied class name and resolve it to a concrete
     * instance of a given class
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $class
     * @return TClass
     */
    protected function getFqcnOptionInstance(
        string $valueName,
        string $value,
        string $class,
        ?string $namespaceEnvVar = null
    ) {
        $value = $this->getFqcnOptionValue($valueName, $value, $namespaceEnvVar);
        if (is_a($value, $class, true)) {
            return $this->App->get($value);
        }
        throw new CliInvalidArgumentsException(
            class_exists($value)
                ? sprintf('class does not inherit %s: %s', $class, $value)
                : sprintf('class does not exist: %s', $value),
        );
    }

    /**
     * Get data from a user-supplied JSON file, optionally assigning its
     * relative path to a variable passed by reference
     *
     * If `$filename` is `"-"`, JSON is read from `STDIN` and `$path` is not
     * modified.
     *
     * @param string|null $path Receives `$filename` relative to the
     * application's base path if possible, otherwise `$filename` itself.
     * @return mixed[]|object
     */
    protected function getJsonOptionData(
        string $filename,
        ?string &$path = null,
        bool $associative = true
    ) {
        if ($filename !== '-') {
            $path = File::getRelativePath($filename, $this->App->getBasePath(), $filename);
        }
        return CliUtil::getJson($filename, $associative);
    }
}
