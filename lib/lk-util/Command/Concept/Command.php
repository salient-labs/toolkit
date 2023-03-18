<?php declare(strict_types=1);

/**
 * @package Lkrms\LkUtil
 */

namespace Lkrms\LkUtil\Command\Concept;

use Lkrms\Cli\Concept\CliCommand;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Contract\IProvider;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\LkUtil\Dictionary\EnvVar;

/**
 * Base class for lk-util commands
 *
 */
abstract class Command extends CliCommand
{
    public function getLongDescription(): ?string
    {
        return null;
    }

    public function getUsageSections(): ?array
    {
        return null;
    }

    /**
     * @return class-string<object>
     */
    protected function getFqcnOptionValue(string $value, ?string $namespaceEnvVar = null, ?string &$class = null, ?string &$namespace = null): string
    {
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
        $class     = Convert::classToBasename($fqcn);
        $namespace = Convert::classToNamespace($fqcn);

        return $fqcn;
    }

    /**
     * @param string[] $values
     * @return array<class-string<object>>
     */
    protected function getMultipleFqcnOptionValue(array $values, ?string $namespaceEnvVar = null): array
    {
        $_values = [];
        foreach ($values as $value) {
            $_values[] = $this->getFqcnOptionValue($value, $namespaceEnvVar);
        }

        return $_values;
    }

    /**
     * @template TProvider of IProvider
     * @param class-string<TProvider> $provider
     * @param class-string<IProvider> $class
     * @return TProvider
     */
    protected function getProvider(string $provider, string $class = IProvider::class): IProvider
    {
        $provider = $this->getFqcnOptionValue($provider, EnvVar::NS_PROVIDER);
        if (is_a($provider, $class, true)) {
            return $this->app()->get($provider);
        }

        throw class_exists($provider)
            ? new CliArgumentsInvalidException("not a subclass of $class: $provider")
            : new CliArgumentsInvalidException("class does not exist: $provider");
    }

    protected function getJson(string $file, ?string &$path = null)
    {
        if ($file === '-') {
            $file = 'php://stdin';
        } elseif (($file = realpath($_file = $file)) === false) {
            throw new CliArgumentsInvalidException("file not found: $_file");
        } elseif (strpos($file, $this->app()->BasePath) === 0) {
            $path = './' . ltrim(substr($file, strlen($this->app()->BasePath)), '/');
        } else {
            $path = $file;
        }

        return json_decode(file_get_contents($file), true);
    }
}
