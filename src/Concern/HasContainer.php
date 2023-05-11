<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Container;

trait HasContainer
{
    /**
     * @var Container
     */
    protected $App;

    public function __construct(Container $app)
    {
        $this->App = $app;
    }

    final public function app(): Container
    {
        return $this->App;
    }

    final public function container(): Container
    {
        return $this->App;
    }
}
