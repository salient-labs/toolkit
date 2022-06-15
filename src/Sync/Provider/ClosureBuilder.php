<?php

declare(strict_types=1);

namespace Lkrms\Sync\Provider;

use Closure;
use ReflectionClass;

class ClosureBuilder
{
    /**
     * @var string
     */
    protected $Class;

    /**
     * @var string[]
     */
    protected $ProviderInterfaces = [];

    /**
     * @var array<string,ClosureBuilder>
     */
    private static $Instances = [];

    /**
     * @var array<string,Closure>
     */
    private static $BindProviderInterfacesClosures = [];

    public static function get(string $class): ClosureBuilder
    {
        if ($instance = self::$Instances[$class] ?? null)
        {
            return $instance;
        }

        $instance = new self($class);
        self::$Instances[$class] = $instance;

        return $instance;
    }

    protected function __construct(string $class)
    {
        $class       = new ReflectionClass($class);
        $this->Class = $class->name;

        if ($class->implementsInterface(ISyncProvider::class))
        {
            foreach ($class->getInterfaces() as $name => $interface)
            {
                if ($interface->isSubclassOf(ISyncProvider::class))
                {
                    $this->ProviderInterfaces[] = $name;
                }
            }
        }
    }

    /**
     * @return Closure
     * ```php
     * closure(\Lkrms\Container\Container $container, bool $invert, string ...$interfaces): void
     * ```
     */
    public function getBindISyncProviderInterfacesClosure(): Closure
    {
        if ($closure = self::$BindProviderInterfacesClosures[$this->Class] ?? null)
        {
            return $closure;
        }

        if ($this->ProviderInterfaces)
        {
            $closure = function (\Lkrms\Container\Container $container, bool $invert, string ...$interfaces): void
            {
                $interfaces = ($invert
                    ? array_diff($this->ProviderInterfaces, $interfaces)
                    : array_intersect($this->ProviderInterfaces, $interfaces ?: $this->ProviderInterfaces));
                foreach ($interfaces as $name)
                {
                    $container->bind($name, $this->Class);
                }
            };
        }
        else
        {
            $closure = static function (): void
            {
            };
        }

        self::$BindProviderInterfacesClosures[$this->Class] = $closure;

        return $closure;
    }

}
