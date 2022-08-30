<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Environment;

/**
 * A facade for Environment
 *
 * @method static void apply() Apply values from the environment to the running script
 * @method static bool debug(?bool $newState = null) Optionally turn debug mode on or off, then return its current state
 * @method static bool dryRun(?bool $newState = null) Optionally turn dry-run mode on or off, then return its current state
 * @method static string|null get(string $name, ?string $default = null) Retrieve an environment variable
 * @method static int|null getInt(string $name, ?int $default = null) Return an environment variable as an integer
 * @method static string[]|null getList(string $name, string[]|null $default = null, string $delimiter = ',') Return an environment variable as a list of strings
 * @method static bool has(string $name) Returns true if a variable exists in the environment
 * @method static void loadFile(string $filename, bool $apply = true) Load environment variables from a file
 * @method static void set(string $name, string $value) Set an environment variable
 * @method static void unset(string $name) Unset an environment variable
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
