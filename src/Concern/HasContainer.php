<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Container;

trait HasContainer
{
    /**
     * @var Container
     */
    private $_Container;

    public function __construct(Container $container)
    {
        $this->_Container = $container;
    }

    final public function app(): Container
    {
        return $this->_Container;
    }

    final public function container(): Container
    {
        return $this->_Container;
    }

}
