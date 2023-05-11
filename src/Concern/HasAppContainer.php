<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\AppContainer;

trait HasAppContainer
{
    /**
     * @var AppContainer
     */
    protected $App;

    public function __construct(AppContainer $app)
    {
        $this->App = $app;
    }

    final public function app(): AppContainer
    {
        return $this->App;
    }

    final public function container(): AppContainer
    {
        return $this->App;
    }
}
