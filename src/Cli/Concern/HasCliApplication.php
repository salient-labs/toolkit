<?php declare(strict_types=1);

namespace Lkrms\Cli\Concern;

use Lkrms\Cli\CliApplication;

trait HasCliApplication
{
    /**
     * @var CliApplication
     */
    private $_Container;

    public function __construct(CliApplication $container)
    {
        $this->_Container = $container;
    }

    final public function app(): CliApplication
    {
        return $this->_Container;
    }

    final public function container(): CliApplication
    {
        return $this->_Container;
    }
}
