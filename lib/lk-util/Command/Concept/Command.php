<?php

declare(strict_types=1);

/**
 * @package Lkrms\LkUtil
 */

namespace Lkrms\LkUtil\Command\Concept;

use Lkrms\Cli\Concept\CliCommand;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Contract\IProvider;
use Lkrms\Facade\Env;

/**
 * Base class for lk-util commands
 *
 */
abstract class Command extends CliCommand
{
    protected function getFqcnOptionValue(string $value, ?string $defaultNamespace = null): string
    {
        if ($defaultNamespace && trim($value) && strpos($value, "\\") === false)
        {
            return trim($defaultNamespace, "\\") . "\\$value";
        }

        return ltrim($value, "\\");
    }

    /**
     * @param string[] $values
     * @return string[]
     */
    protected function getMultipleFqcnOptionValue(array $values, ?string $defaultNamespace = null): array
    {
        $_values = [];
        foreach ($values as $value)
        {
            $_values[] = $this->getFqcnOptionValue($value, $defaultNamespace);
        }

        return $_values;
    }

    protected function getProvider(string $provider, string $class = IProvider::class): IProvider
    {
        $provider = $this->getFqcnOptionValue($provider, Env::get("PROVIDER_NAMESPACE", null));
        if (is_a($provider, $class, true))
        {
            return $this->app()->get($provider);
        }

        throw class_exists($provider)
            ? new CliArgumentsInvalidException("not a subclass of $class: $provider")
            : new CliArgumentsInvalidException("class does not exist: $provider");
    }

    protected function getJson(string $file, ?string & $path = null)
    {
        if ($file === "-")
        {
            $file = "php://stdin";
        }
        elseif (($file = realpath($_file = $file)) === false)
        {
            throw new CliArgumentsInvalidException("file not found: $_file");
        }
        elseif (strpos($file, $this->app()->BasePath) === 0)
        {
            $path = "./" . ltrim(substr($file, strlen($this->app()->BasePath)), "/");
        }
        else
        {
            $path = $file;
        }

        return json_decode(file_get_contents($file), true);
    }

}
