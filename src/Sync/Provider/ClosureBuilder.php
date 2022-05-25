<?php

declare(strict_types=1);

namespace Lkrms\Sync\Provider;

use Closure;
use Lkrms\Ioc\Ioc;
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

    public static function getFor(string $class): ClosureBuilder
    {
        $class = Ioc::resolve($class);

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

    public function getBindProviderInterfacesClosure(): Closure
    {
        if ($closure = self::$BindProviderInterfacesClosures[$this->Class] ?? null)
        {
            return $closure;
        }

        if ($this->ProviderInterfaces)
        {
            $closure = function ()
            {
                foreach ($this->ProviderInterfaces as $name)
                {
                    Ioc::register($name, $this->Class);
                }
            };
        }
        else
        {
            $closure = static function ()
            {
            };
        }

        self::$BindProviderInterfacesClosures[$this->Class] = $closure;

        return $closure;
    }

}
