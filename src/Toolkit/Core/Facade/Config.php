<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Core\ConfigurationManager;

/**
 * A facade for ConfigurationManager
 *
 * @method static array<string,mixed[]> all() Get all configuration values
 * @method static mixed get(string $key, mixed $default = null) Get a configuration value
 * @method static array<string,mixed> getMultiple(iterable<string> $keys, mixed $default = null) Get multiple configuration values
 * @method static bool has(string $key) Check if a configuration value exists
 * @method static ConfigurationManager loadDirectory(string $directory) Load values from files in a directory
 *
 * @api
 *
 * @extends Facade<ConfigurationManager>
 *
 * @generated
 */
final class Config extends Facade
{
    /**
     * @internal
     */
    protected static function getService()
    {
        return ConfigurationManager::class;
    }
}
