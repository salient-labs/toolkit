<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Core\Exception\InvalidConfigurationException;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Pcre;
use ArrayAccess;
use LogicException;
use OutOfRangeException;
use ReturnTypeWillChange;

/**
 * Provides access to values in configuration files
 *
 * @api
 *
 * @implements ArrayAccess<string,mixed>
 */
final class ConfigurationManager implements ArrayAccess
{
    /**
     * @var array<string,mixed[]>
     */
    private array $Items = [];

    /**
     * @param array<string,mixed[]> $items
     */
    public function __construct(array $items = [])
    {
        $this->Items = $items;
    }

    /**
     * Load values from files in a directory
     *
     * @return $this
     */
    public function loadDirectory(string $directory): self
    {
        $items = [];
        foreach (File::find()->in($directory)->include('/\.php$/')->doNotRecurse() as $file) {
            $basename = $file->getBasename('.php');
            $file = (string) $file;
            if (Pcre::match('/[\s.]|^[0-9]+$/', $basename)) {
                throw new InvalidConfigurationException(sprintf(
                    'Invalid configuration file name: %s',
                    $file,
                ));
            }
            $values = require $file;
            if (!is_array($values)) {
                throw new InvalidConfigurationException(sprintf(
                    'Invalid configuration file: %s',
                    $file,
                ));
            }
            $items[$basename] = $values;
        }
        ksort($items, \SORT_NATURAL);
        $this->Items = $items;
        return $this;
    }

    /**
     * Check if a configuration value exists
     */
    public function has(string $key): bool
    {
        return Arr::has($key, $this->Items);
    }

    /**
     * Get a configuration value
     *
     * @param mixed $default
     * @return mixed
     * @throws OutOfRangeException if `$key` is not configured and no `$default`
     * is given.
     */
    public function get(string $key, $default = null)
    {
        if (func_num_args() > 1) {
            return Arr::get($key, $this->Items, $default);
        }
        return Arr::get($key, $this->Items);
    }

    /**
     * Get multiple configuration values
     *
     * @param array<string|int,mixed|string> $keys An array that optionally maps
     * keys to default values.
     * @return array<string,mixed>
     * @throws OutOfRangeException if a key is not configured and no default
     * value is given.
     */
    public function getMany(array $keys): array
    {
        foreach ($keys as $key => $default) {
            if (is_int($key)) {
                $key = $default;
                $values[$key] = Arr::get($key, $this->Items);
                continue;
            }
            $values[$key] = Arr::get($key, $this->Items, $default);
        }
        return $values ?? [];
    }

    /**
     * Get all configuration values
     *
     * @return array<string,mixed[]>
     */
    public function all(): array
    {
        return $this->Items;
    }

    /**
     * @internal
     *
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return Arr::get($offset, $this->Items, null) !== null;
    }

    /**
     * @internal
     *
     * @param string $offset
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return Arr::get($offset, $this->Items);
    }

    /**
     * @internal
     *
     * @param string $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        throw new LogicException('Configuration values are read-only');
    }

    /**
     * @internal
     *
     * @param string $offset
     */
    public function offsetUnset($offset): void
    {
        throw new LogicException('Configuration values are read-only');
    }
}
