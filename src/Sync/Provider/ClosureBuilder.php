<?php

declare(strict_types=1);

namespace Lkrms\Sync\Provider;

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
     * @return string[]
     */
    public function getSyncProviderInterfaces(): array
    {
        return $this->ProviderInterfaces;
    }

}
