<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

trait MyInstanceTrait
{
    /**
     * @var mixed[]
     */
    private array $Args;

    private int $Clones = 0;

    /**
     * @var static[]
     */
    private static array $Unloaded = [];

    /**
     * @param mixed ...$args
     */
    public function __construct(...$args)
    {
        $this->Args = $args;
    }

    public function __clone()
    {
        $this->Clones++;
    }

    /**
     * @return mixed[]
     */
    public function getArgs(): array
    {
        return $this->Args;
    }

    public function getClones(): int
    {
        return $this->Clones;
    }

    /**
     * @param mixed ...$args
     * @return static
     */
    public function withArgs(...$args)
    {
        if ($this->Args === $args) {
            return $this;
        }
        $instance = clone $this;
        $instance->Args = $args;
        return $instance;
    }

    public function unload(): void
    {
        self::$Unloaded[] = $this;
    }

    /**
     * @return static[]
     */
    public static function getUnloaded(): array
    {
        return self::$Unloaded;
    }

    public static function reset(): void
    {
        self::$Unloaded = [];
    }
}
