<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Concept;

use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\CliCommand;
use Lkrms\Contract\IProvider;
use Lkrms\LkUtil\Catalog\EnvVar;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Env;
use Lkrms\Utility\File;
use JsonException;

/**
 * Base class for lk-util commands
 */
abstract class Command extends CliCommand
{
    public function getLongDescription(): ?string
    {
        return null;
    }

    public function getHelpSections(): ?array
    {
        return null;
    }

    /**
     * Normalise a user-supplied class name, optionally assigning its base name
     * and/or namespace to variables passed by reference
     *
     * @return class-string<object>
     */
    protected function getFqcnOptionValue(
        string $value,
        ?string $namespaceEnvVar = null,
        ?string &$class = null,
        ?string &$namespace = null
    ): string {
        $namespace = null;
        if ($namespaceEnvVar) {
            $namespace = Env::get($namespaceEnvVar, null);
        }
        if (is_null($namespace)) {
            $namespace = Env::get(EnvVar::NS_DEFAULT, null);
        }
        if ($namespace && trim($value) && strpos($value, '\\') === false) {
            $fqcn = trim($namespace, '\\') . "\\$value";
        } else {
            $fqcn = ltrim($value, '\\');
        }
        $class = Convert::classToBasename($fqcn);
        $namespace = Convert::classToNamespace($fqcn);

        return $fqcn;
    }

    /**
     * Normalise a mandatory user-supplied class name, optionally assigning its
     * base name and/or namespace to variables passed by reference
     *
     * @return class-string<object>
     */
    protected function getRequiredFqcnOptionValue(
        string $valueName,
        string $value,
        ?string $namespaceEnvVar = null,
        ?string &$class = null,
        ?string &$namespace = null
    ): string {
        $fqcn = $this->getFqcnOptionValue($value, $namespaceEnvVar, $class, $namespace);
        if (!$fqcn) {
            throw new CliInvalidArgumentsException(sprintf('invalid %s: %s', $valueName, $value));
        }

        return $fqcn;
    }

    /**
     * Normalise user-supplied class names
     *
     * @param string[] $values
     * @return array<class-string<object>>
     */
    protected function getMultipleFqcnOptionValue(array $values, ?string $namespaceEnvVar = null): array
    {
        $fqcn = [];
        foreach ($values as $value) {
            $fqcn[] = $this->getFqcnOptionValue($value, $namespaceEnvVar);
        }

        return $fqcn;
    }

    /**
     * Resolve a user-supplied provider name to a concrete instance
     *
     * @template TBaseProvider of IProvider
     * @template TProvider of TBaseProvider
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
            $file = File::realpath($file);

            if ($file === false) {
                throw new CliInvalidArgumentsException(sprintf(
                    'file not found: %s',
                    $_file,
                ));
            }

            if (strpos($file, $this->App->getBasePath()) === 0) {
                $path = './' . substr($file, strlen($this->App->getBasePath()) + 1);
            } else {
                $path = $file;
            }
        }

        try {
            return json_decode(file_get_contents($file), $associative, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $ex) {
            throw new CliInvalidArgumentsException(sprintf(
                "invalid JSON in '%s': %s",
                $_file,
                $ex->getMessage(),
            ));
        }
    }
}
