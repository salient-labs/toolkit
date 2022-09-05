<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\CliAppContainer;

trait HasCliAppContainer
{
    use HasContainer;

    /**
     * @var CliAppContainer
     */
    private $Container;

    public function __construct(CliAppContainer $container)
    {
        $this->Container = $container;
    }

    public function app(): CliAppContainer
    {
        return $this->Container;
    }

    public function container(): CliAppContainer
    {
        return $this->Container;
    }

}
