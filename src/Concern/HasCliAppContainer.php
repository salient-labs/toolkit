<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Cli\CliAppContainer;

trait HasCliAppContainer
{
    /**
     * @var CliAppContainer
     */
    private $Container;

    public function __construct(CliAppContainer $container)
    {
        $this->Container = $container;
    }

    final public function app(): CliAppContainer
    {
        return $this->Container;
    }

    final public function container(): CliAppContainer
    {
        return $this->Container;
    }

}
