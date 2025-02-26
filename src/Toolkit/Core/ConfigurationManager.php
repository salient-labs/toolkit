<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\Instantiable;
use Salient\Core\Exception\InvalidConfigurationException;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Regex;
use ArrayAccess;
use LogicException;
use OutOfRangeException;
use ReturnTypeWillChange;

/**
 * @api
 *
 * @implements ArrayAccess<string,mixed>
 */
final class ConfigurationManager implements ArrayAccess, Instantiable
{
    /** @var array<string,mixed[]> */
    private array $Items = [];

    /**
     * @internal
     *
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
        $files = File::find()
            ->files()
            ->in($directory)
            ->include('/\.php$/')
            ->doNotRecurse();

        $items = [];
        foreach ($files as $file) {
            $basename = $file->getBasename('.php');
            $file = (string) $file;
            if (Regex::match('/[\s.]|^[0-9]+$/', $basename)) {
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
        return Arr::has($this->Items, $key);
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
            return Arr::get($this->Items, $key, $default);
        }
        return Arr::get($this->Items, $key);
    }

    /**
     * Get multiple configuration values
     *
     * @param iterable<string> $keys
     * @param mixed $default
     * @return array<string,mixed>
     * @throws OutOfRangeException if a key is not configured and no `$default`
     * is given.
     */
    public function getMultiple(iterable $keys, $default = null): array
    {
        if (func_num_args() > 1) {
            foreach ($keys as $key) {
                $values[$key] = Arr::get($this->Items, $key, $default);
            }
        } else {
            foreach ($keys as $key) {
                $values[$key] = Arr::get($this->Items, $key);
            }
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
        return Arr::get($this->Items, $offset, null) !== null;
    }

    /**
     * @internal
     *
     * @param string $offset
     * @return mixed
     * @disregard P1038
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return Arr::get($this->Items, $offset);
    }

    /**
     * @internal
     *
     * @param string|null $offset
     * @param mixed $value
     * @return never
     */
    public function offsetSet($offset, $value): void
    {
        throw new LogicException('Configuration values cannot be changed at runtime');
    }

    /**
     * @internal
     *
     * @param string $offset
     * @return never
     */
    public function offsetUnset($offset): void
    {
        throw new LogicException('Configuration values cannot be changed at runtime');
    }
}
