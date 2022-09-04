<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Environment;

/**
 * A facade for \Lkrms\Utility\Environment
 *
 * @method static Environment load() Load and return an instance of the underlying `Environment` class
 * @method static Environment getInstance() Return the underlying `Environment` instance
 * @method static bool isLoaded() Return true if an underlying `Environment` instance has been loaded
 * @method static void unload() Clear the underlying `Environment` instance
 * @method static void apply() Apply values from the environment to the running script (see {@see Environment::apply()})
 * @method static bool debug(?bool $newState = null) Optionally turn debug mode on or off, then return its current state (see {@see Environment::debug()})
 * @method static bool dryRun(?bool $newState = null) Optionally turn dry-run mode on or off, then return its current state (see {@see Environment::dryRun()})
 * @method static string|null get(string $name, ?string $default = null) Retrieve an environment variable (see {@see Environment::get()})
 * @method static int|null getInt(string $name, ?int $default = null) Return an environment variable as an integer (see {@see Environment::getInt()})
 * @method static string[]|null getList(string $name, string[]|null $default = null, string $delimiter = ',') Return an environment variable as a list of strings (see {@see Environment::getList()})
 * @method static bool has(string $name) Returns true if a variable exists in the environment (see {@see Environment::has()})
 * @method static void loadFile(string $filename, bool $apply = true) Load environment variables from a file (see {@see Environment::loadFile()})
 * @method static void set(string $name, string $value) Set an environment variable (see {@see Environment::set()})
 * @method static void unset(string $name) Unset an environment variable (see {@see Environment::unset()})
 *
 * @uses Environment
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\Environment' --generate='Lkrms\Facade\Env'
 */
final class Env extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Environment::class;
    }
}
