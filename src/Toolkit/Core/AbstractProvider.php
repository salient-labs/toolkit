<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\ProviderContextInterface;
use Salient\Contract\Core\ProviderInterface;
use Salient\Core\Exception\MethodNotImplementedException;

/**
 * Base class for providers
 *
 * @implements ProviderInterface<ProviderContext<$this,AbstractEntity>>
 */
abstract class AbstractProvider implements ProviderInterface
{
    protected ContainerInterface $App;
    private DateFormatterInterface $DateFormatter;

    /**
     * Creates a new provider object
     */
    public function __construct(ContainerInterface $app)
    {
        $this->App = $app;
    }

    /**
     * @inheritDoc
     */
    final public function getContainer(): ContainerInterface
    {
        return $this->App;
    }

    /**
     * @inheritDoc
     */
    final public function getDateFormatter(): DateFormatterInterface
    {
        return $this->DateFormatter ??= $this->createDateFormatter();
    }

    /**
     * Get a date formatter to work with the backend's date and time format
     * and/or timezone
     *
     * The {@see DateFormatterInterface} returned will be cached for the
     * lifetime of the {@see Provider} instance.
     */
    abstract protected function createDateFormatter(): DateFormatterInterface;

    /**
     * Check if the date formatter returned by getDateFormatter() has been
     * cached
     */
    final protected function hasDateFormatter(): bool
    {
        return isset($this->DateFormatter);
    }

    /**
     * Set or unset the date formatter returned by getDateFormatter()
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

    /**
     * @inheritDoc
     */
    public function getContext(): ProviderContextInterface
    {
        /** @var ProviderContext<$this,AbstractEntity> */
        return new ProviderContext($this->App, $this);
    }

    /**
     * @inheritDoc
     *
     * @codeCoverageIgnore
     */
    public function checkHeartbeat(int $ttl = 300)
    {
        throw new MethodNotImplementedException(
            static::class,
            __FUNCTION__,
            ProviderInterface::class
        );
    }
}
