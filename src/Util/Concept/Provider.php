<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Container\ContainerInterface;
use Lkrms\Contract\IProvider;
use Lkrms\Support\Date\DateFormatterInterface;
use Lkrms\Support\ProviderContext;
use Salient\Core\Exception\MethodNotImplementedException;

/**
 * Base class for providers
 *
 * @implements IProvider<ProviderContext>
 */
abstract class Provider implements IProvider
{
    protected ContainerInterface $App;

    private DateFormatterInterface $DateFormatter;

    /**
     * Creates a new Provider object
     */
    public function __construct(ContainerInterface $app)
    {
        $this->App = $app;
    }

    /**
     * Get a date formatter to work with the backend's date and time format
     * and/or timezone
     *
     * The {@see DateFormatterInterface} returned will be cached for the
     * lifetime of the {@see Provider} instance.
     */
    abstract protected function getDateFormatter(): DateFormatterInterface;

    /**
     * @inheritDoc
     */
    public function getContext(?ContainerInterface $container = null): ProviderContext
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
    final public function app(): ContainerInterface
    {
        return $this->App;
    }

    /**
     * @inheritDoc
     */
    final public function container(): ContainerInterface
    {
        return $this->App;
    }

    /**
     * @inheritDoc
     */
    final public function dateFormatter(): DateFormatterInterface
    {
        return $this->DateFormatter
            ?? ($this->DateFormatter = $this->getDateFormatter());
    }

    /**
     * Get the date formatter cached by dateFormatter(), or null if it hasn't
     * been cached
     */
    final protected function getCachedDateFormatter(): ?DateFormatterInterface
    {
        return $this->DateFormatter ?? null;
    }

    /**
     * Set or unset the date formatter returned by dateFormatter()
     *
     * @return $this
     */
    final protected function setDateFormatter(?DateFormatterInterface $formatter)
    {
        if ($formatter === null) {
            unset($this->DateFormatter);
        } else {
            $this->DateFormatter = $formatter;
        }

        return $this;
    }
}
