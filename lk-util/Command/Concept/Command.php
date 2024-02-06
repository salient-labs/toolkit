<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Concept;

use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\CliCommand;
use Lkrms\Contract\IProvider;
use Lkrms\Exception\FilesystemErrorException;
use Lkrms\LkUtil\Catalog\EnvVar;
use Lkrms\Utility\Env;
use Lkrms\Utility\File;
use Lkrms\Utility\Get;
use Lkrms\Utility\Json;
use JsonException;

/**
 * Base class for lk-util commands
 */
abstract class Command extends CliCommand
{
    /**
     * Normalise a user-supplied class name, optionally assigning its base name
     * and/or namespace to variables passed by reference
     *
     * @return class-string|''
     */
    protected function getFqcnOptionValue(
        string $value,
        ?string $namespaceEnvVar = null,
        ?string &$class = null,
        ?string &$namespace = null
    ): string {
        $namespace = null;
        if ($namespaceEnvVar !== null) {
            $namespace = Env::get($namespaceEnvVar, null);
        }
        if ($namespace === null) {
            $namespace = Env::get(EnvVar::NS_DEFAULT, null);
        }
        if ($namespace !== null && trim($value) && strpos($value, '\\') === false) {
            $fqcn = trim($namespace, '\\') . "\\$value";
        } else {
            $fqcn = ltrim($value, '\\');
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
    protected function getRequiredFqcnOptionValue(
        string $valueName,
        string $value,
        ?string $namespaceEnvVar = null,
        ?string &$class = null,
        ?string &$namespace = null
    ): string {
        $fqcn = $this->getFqcnOptionValue($value, $namespaceEnvVar, $class, $namespace);

        if ($fqcn === '') {
            throw new CliInvalidArgumentsException(sprintf('invalid %s: %s', $valueName, $value));
        }

        return $fqcn;
    }

    /**
     * Normalise mandatory user-supplied class names
     *
     * @param string[] $values
     * @return array<class-string>
     */
    protected function requireMultipleFqcnValues(
        string $valueName,
        array $values,
        ?string $namespaceEnvVar = null
    ): array {
        $fqcn = [];
        foreach ($values as $value) {
            $fqcn[] = $this->getRequiredFqcnOptionValue($valueName, $value, $namespaceEnvVar);
        }
        return $fqcn;
    }

    /**
     * Resolve a user-supplied provider name to a concrete instance
     *
     * @template TBaseProvider of IProvider
     * @template TProvider of TBaseProvider
     *
     * @param class-string<TProvider> $provider
     * @param class-string<TBaseProvider> $class
     * @return TProvider
     */
    protected function getProvider(string $provider, string $class = IProvider::class): IProvider
    {
        $provider = $this->getFqcnOptionValue($provider, EnvVar::NS_PROVIDER);
        if (is_a($provider, $class, true)) {
            return $this->App->get($provider);
        }

        throw class_exists($provider)
            ? new CliInvalidArgumentsException("not a subclass of $class: $provider")
            : new CliInvalidArgumentsException("class does not exist: $provider");
    }

    /**
     * Get data from a user-supplied JSON file, optionally assigning the file's
     * "friendly pathname" to a variable before returning
     *
     * @return mixed
     */
    protected function getJson(string $file, ?string &$path = null, bool $associative = true)
    {
        $_file = $file;
        if ($file === '-') {
            $file = 'php://stdin';
        } else {
            try {
                $file = File::realpath($file);
            } catch (FilesystemErrorException $ex) {
                throw new CliInvalidArgumentsException(sprintf(
                    'file not found: %s',
                    $_file,
                ));
            }

            $relative = File::relativeToParent($file, $this->App->getBasePath());
            $path = $relative === null ? $file : "./{$relative}";
        }

        $json = File::getContents($file);

        try {
            return $associative
                ? Json::parseObjectAsArray($json)
                : Json::parse($json);
        } catch (JsonException $ex) {
            throw new CliInvalidArgumentsException(sprintf(
                "invalid JSON in '%s': %s",
                $_file,
                $ex->getMessage(),
            ));
        }
    }
}
