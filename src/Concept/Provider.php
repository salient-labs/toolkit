<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProvider;
use Lkrms\Contract\IProviderContext;
use Lkrms\Exception\MethodNotImplementedException;
use Lkrms\Support\DateFormatter;
use Lkrms\Support\ProviderContext;
use Lkrms\Utility\Env;

/**
 * Base class for providers
 *
 */
abstract class Provider implements IProvider
{
    protected IContainer $App;

    protected Env $Env;

    private DateFormatter $DateFormatter;

    /**
     * Creates a new provider object
     */
    public function __construct(IContainer $app, Env $env)
    {
        $this->App = $app;
        $this->Env = $env;
    }

    /**
     * Get a DateFormatter to work with the backend's date format and/or
     * timezone
     *
     * The {@see DateFormatter} returned will be cached for the lifetime of the
     * {@see Provider} instance.
     */
    abstract protected function getDateFormatter(): DateFormatter;

    /**
     * @inheritDoc
     */
    public function description(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getContext(?IContainer $container = null): IProviderContext
    {
        if (!$container) {
            $container = $this->App;
        }

        return $container
            ->bindIf(IProviderContext::class, ProviderContext::class)
            ->get(IProviderContext::class, [$this]);
    }

    /**
     * @inheritDoc
     */
    public function checkHeartbeat(int $ttl = 300)
    {
        throw new MethodNotImplementedException(
            static::class,
            __FUNCTION__,
            IProvider::class
        );
    }

    /**
     * @inheritDoc
     */
    final public function app(): IContainer
    {
        return $this->App;
    }

    /**
     * @inheritDoc
     */
    final public function container(): IContainer
    {
        return $this->App;
    }

    /**
     * @inheritDoc
     */
    final public function env(): Env
    {
        return $this->Env;
    }

    /**
     * @inheritDoc
     */
    final public function dateFormatter(): DateFormatter
    {
        return $this->DateFormatter
            ?? ($this->DateFormatter = $this->getDateFormatter());
    }
}
