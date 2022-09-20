<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\AppContainer;

trait HasAppContainer
{
    /**
     * @var AppContainer
     */
    private $Container;

    public function __construct(AppContainer $container)
    {
        $this->Container = $container;
    }

    public function app(): AppContainer
    {
        return $this->Container;
    }

    public function container(): AppContainer
    {
        return $this->Container;
    }

}
