<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\ReturnsContainer;

/**
 * Returns an injected service container
 *
 * Implements {@see ReturnsContainer}.
 *
 * @template T of IContainer
 */
trait HasContainer
{
    /**
     * @var T
     */
    protected $App;

    /**
     * @param T $app
     */
    public function __construct(IContainer $app)
    {
        $this->App = $app;
    }

    /**
     * @return T
     */
    final public function app(): IContainer
    {
        return $this->App;
    }

    /**
     * @return T
     */
    final public function container(): IContainer
    {
        return $this->App;
    }
}
