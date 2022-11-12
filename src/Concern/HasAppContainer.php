<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\AppContainer;

trait HasAppContainer
{
    /**
     * @var AppContainer
     */
    private $_Container;

    public function __construct(AppContainer $container)
    {
        $this->_Container = $container;
    }

    final public function app(): AppContainer
    {
        return $this->_Container;
    }

    final public function container(): AppContainer
    {
        return $this->_Container;
    }

}
