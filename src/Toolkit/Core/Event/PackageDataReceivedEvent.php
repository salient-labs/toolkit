<?php declare(strict_types=1);

namespace Salient\Core\Event;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;

/**
 * Dispatched when package data is received from the Composer runtime API
 *
 * @template TData
 */
final class PackageDataReceivedEvent
{
    /**
     * @var TData
     */
    private $Data;

    /**
     * @var class-string<InstalledVersions|ClassLoader>
     */
    private string $Class;

    private string $Method;

    /**
     * @var mixed[]
     */
    private array $Arguments;

    /**
     * @param TData $data
     * @param class-string<InstalledVersions|ClassLoader> $class
     * @param mixed ...$args
     */
    public function __construct(
        $data,
        string $method,
        string $class = InstalledVersions::class,
        ...$args
    ) {
        $this->Data = $data;
        $this->Class = $class;
        $this->Method = $method;
        $this->Arguments = $args;
    }

    /**
     * True if the given Composer runtime API method was called
     *
     * @param class-string<InstalledVersions|ClassLoader> $class
     */
    public function isMethod(string $method, string $class = InstalledVersions::class): bool
    {
        return strcasecmp($class, $this->Class) === 0 &&
            strcasecmp($method, $this->Method) === 0;
    }

    /**
     * Get arguments passed to the Composer runtime API when the method was
     * called
     *
     * @return mixed[]
     */
    public function getArguments(): array
    {
        return $this->Arguments;
    }

    /**
     * Get data received from the Composer runtime API
     *
     * @return TData
     */
    public function getData()
    {
        return $this->Data;
    }

    /**
     * Replace data received from the Composer runtime API
     *
     * @param TData $data
     */
    public function setData($data): void
    {
        $this->Data = $data;
    }
}
