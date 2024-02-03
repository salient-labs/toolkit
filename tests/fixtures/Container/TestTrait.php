<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Container\Contract\ContainerInterface;
use Lkrms\Container\Container;
use LogicException;

trait TestTrait
{
    protected ?ContainerInterface $Container = null;

    protected ?string $Service = null;

    public function service(): string
    {
        return $this->Service ?? static::class;
    }

    public function app(): ContainerInterface
    {
        return $this->container();
    }

    public function container(): ContainerInterface
    {
        return $this->Container ?: ($this->Container = new Container());
    }

    public function setContainer(ContainerInterface $container): void
    {
        if ($this->Container) {
            throw new LogicException('setContainer already called');
        }
        $this->Container = $container;
    }

    public function setService(string $service): void
    {
        if ($this->Service) {
            throw new LogicException('setService already called');
        }
        $this->Service = $service;
    }
}
