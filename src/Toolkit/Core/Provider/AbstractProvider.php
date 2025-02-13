<?php declare(strict_types=1);

namespace Salient\Core\Provider;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Provider\ProviderContextInterface;
use Salient\Contract\Core\Provider\ProviderInterface;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Core\Date\DateFormatter;
use Salient\Core\Exception\MethodNotImplementedException;

/**
 * @api
 *
 * @implements ProviderInterface<ProviderContext<$this,AbstractEntity>>
 */
abstract class AbstractProvider implements ProviderInterface
{
    protected ContainerInterface $App;

    /**
     * @api
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
    public function getDateFormatter(): DateFormatterInterface
    {
        return new DateFormatter();
    }

    /**
     * @inheritDoc
     */
    public function getContext(): ProviderContextInterface
    {
        /** @var ProviderContext<$this,AbstractEntity> */
        return new ProviderContext($this, $this->App);
    }

    /**
     * @inheritDoc
     */
    public function checkHeartbeat(int $ttl = 300)
    {
        throw new MethodNotImplementedException(
            static::class,
            __FUNCTION__,
            ProviderInterface::class,
        );
    }

    /** @deprecated Override {@see getDateFormatter()} instead. */
    final protected function createDateFormatter(): void {}
}
