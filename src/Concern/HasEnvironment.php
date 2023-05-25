<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\ReturnsEnvironment;
use Lkrms\Utility\Environment;

/**
 * Returns a shared Environment instance
 *
 * Implements {@see ReturnsEnvironment}.
 *
 * @template T of IContainer
 */
trait HasEnvironment
{
    /**
     * @use HasContainer<T>
     */
    use HasContainer;

    /**
     * @var Environment
     */
    protected $Env;

    /**
     * @param T $app
     */
    public function __construct(IContainer $app)
    {
        $this->App = $app->singletonIf(Environment::class);
        $this->Env = $this->App->get(Environment::class);
    }

    final public function env(): Environment
    {
        return $this->Env;
    }
}
