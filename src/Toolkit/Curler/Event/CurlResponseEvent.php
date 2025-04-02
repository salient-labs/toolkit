<?php declare(strict_types=1);

namespace Salient\Curler\Event;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\Event\CurlResponseEvent as CurlResponseEventInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Http\Message\HttpResponseInterface;
use CurlHandle;

/**
 * @internal
 */
class CurlResponseEvent extends CurlEvent implements CurlResponseEventInterface
{
    protected RequestInterface $Request;
    protected HttpResponseInterface $Response;

    /**
     * @param CurlHandle|resource $curlHandle
     */
    public function __construct(CurlerInterface $curler, $curlHandle, RequestInterface $request, HttpResponseInterface $response)
    {
        $this->Request = $request;
        $this->Response = $response;

        parent::__construct($curler, $curlHandle);
    }

    /**
     * @inheritDoc
     */
    public function getRequest(): RequestInterface
    {
        return $this->Request;
    }

    /**
     * @inheritDoc
     */
    public function getResponse(): HttpResponseInterface
    {
        return $this->Response;
    }
}
