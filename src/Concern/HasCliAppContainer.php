<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Cli\CliAppContainer;

trait HasCliAppContainer
{
    /**
     * @var CliAppContainer
     */
    private $_Container;

    public function __construct(CliAppContainer $container)
    {
        $this->_Container = $container;
    }

    final public function app(): CliAppContainer
    {
        return $this->_Container;
    }

    final public function container(): CliAppContainer
    {
        return $this->_Container;
    }
}
