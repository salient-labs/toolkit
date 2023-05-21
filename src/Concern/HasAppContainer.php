<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Application;

trait HasAppContainer
{
    /**
     * @var Application
     */
    protected $App;

    public function __construct(Application $app)
    {
        $this->App = $app;
    }

    final public function app(): Application
    {
        return $this->App;
    }

    final public function container(): Application
    {
        return $this->App;
    }
}
