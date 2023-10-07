<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use RuntimeException;

trait TestTrait
{
    protected ?IContainer $Container = null;

    protected ?string $Service = null;

    public function service(): string
    {
        return $this->Service ?? static::class;
    }

    public function app(): IContainer
    {
        return $this->container();
    }

    public function container(): IContainer
    {
        return $this->Container ?: ($this->Container = new Container());
    }

    public function setContainer(IContainer $container)
    {
        if ($this->Container) {
            throw new RuntimeException('setContainer already called');
        }
        $this->Container = $container;

        return $this;
    }

    public function setService(string $service)
    {
        if ($this->Service) {
            throw new RuntimeException('setService already called');
        }
        $this->Service = $service;

        return $this;
    }
}
