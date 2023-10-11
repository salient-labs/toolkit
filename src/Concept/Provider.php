<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\IDateFormatter;
use Lkrms\Contract\IProvider;
use Lkrms\Exception\MethodNotImplementedException;
use Lkrms\Support\ProviderContext;
use Lkrms\Utility\Env;

/**
 * Base class for providers
 *
 * @implements IProvider<ProviderContext>
 */
abstract class Provider implements IProvider
{
    protected IContainer $App;

    protected Env $Env;

    private IDateFormatter $DateFormatter;

    /**
     * Creates a new provider object
     */
    public function __construct(IContainer $app, Env $env)
    {
        $this->App = $app;
        $this->Env = $env;
    }

    /**
     * Get a date formatter to work with the backend's date and time format
     * and/or timezone
     *
     * The {@see IDateFormatter} returned will be cached for the lifetime of the
     * {@see Provider} instance.
     */
    abstract protected function getDateFormatter(): IDateFormatter;

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
    public function getContext(?IContainer $container = null): ProviderContext
    {
        if (!$container) {
            $container = $this->App;
        }

        return $container->get(ProviderContext::class, [$this]);
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
    final public function dateFormatter(): IDateFormatter
    {
        return $this->DateFormatter
            ?? ($this->DateFormatter = $this->getDateFormatter());
    }
}
