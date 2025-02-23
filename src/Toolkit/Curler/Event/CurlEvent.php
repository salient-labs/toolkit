<?php declare(strict_types=1);

namespace Salient\Curler\Event;

use Salient\Contract\Curler\Event\CurlEvent as CurlEventInterface;
use Salient\Contract\Curler\CurlerInterface;
use CurlHandle;

/**
 * @internal
 */
abstract class CurlEvent extends CurlerEvent implements CurlEventInterface
{
    /** @var CurlHandle|resource */
    protected $CurlHandle;

    /**
     * @param CurlHandle|resource $curlHandle
     */
    public function __construct(CurlerInterface $curler, $curlHandle)
    {
        $this->CurlHandle = $curlHandle;

        parent::__construct($curler);
    }

    /**
     * @inheritDoc
     */
    public function getCurlHandle()
    {
        return $this->CurlHandle;
    }
}
