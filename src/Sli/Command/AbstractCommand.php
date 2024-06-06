<?php declare(strict_types=1);

namespace Salient\Sli\Command;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliCommand;
use Salient\Core\Utility\Exception\FilesystemErrorException;
use Salient\Core\Utility\Env;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Json;
use Salient\Core\Utility\Test;
use Salient\Sli\EnvVar;
use JsonException;

/**
 * Base class for sli commands
 */
abstract class AbstractCommand extends CliCommand
{
    /**
     * Normalise a user-supplied class name, optionally assigning its base name
     * and/or namespace to variables passed by reference
     *
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
            return '';
        }

        $namespace = null;
        if ($namespaceEnvVar !== null) {
            $namespace = Env::get($namespaceEnvVar, null);
        }
        $namespace ??= Env::get(EnvVar::NS_DEFAULT, null);

        if ($namespace !== null && strpos($value, '\\') === false) {
            $fqcn = trim($namespace, '\\') . "\\$value";
        } else {
            $fqcn = ltrim($value, '\\');
        }

        if (!Test::isFqcn($fqcn)) {
            throw new CliInvalidArgumentsException(sprintf(
                'invalid %s: %s',
                $valueName,
                $value,
            ));
        }

        $class = Get::basename($fqcn);
        $namespace = Get::namespace($fqcn);

        return $fqcn;
    }

    /**
     * Normalise a mandatory user-supplied class name, optionally assigning its
     * base name and/or namespace to variables passed by reference
     *
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
            throw new CliInvalidArgumentsException(sprintf('invalid %s: %s', $valueName, $value));
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
        $fqcn = [];
        foreach ($values as $value) {
            $fqcn[] = $this->requireFqcnOptionValue($valueName, $value, $namespaceEnvVar);
        }
        return $fqcn;
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
            class_exists($value) ? sprintf(
                'class does not inherit %s: %s',
                $class,
                $value,
            ) : sprintf(
                'class does not exist: %s',
                $value,
            )
        );
    }

    /**
     * Get data from a user-supplied JSON file, optionally assigning the file's
     * "friendly pathname" to a variable passed by reference
     *
     * @return mixed
     */
    protected function getJson(string $file, ?string &$path = null, bool $associative = true)
    {
        if ($file === '-') {
            $file = 'php://stdin';
        } else {
            try {
                $path = File::relativeToParent($file, $this->App->getBasePath(), $file);
            } catch (FilesystemErrorException $ex) {
                throw new CliInvalidArgumentsException(sprintf(
                    'file not found: %s',
                    $file,
                ));
            }
        }

        $json = File::getContents($file);

        try {
            return $associative
                ? Json::parseObjectAsArray($json)
                : Json::parse($json);
        } catch (JsonException $ex) {
            throw new CliInvalidArgumentsException(sprintf(
                "invalid JSON in '%s': %s",
                $file,
                $ex->getMessage(),
            ));
        }
    }
}
