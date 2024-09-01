<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Exception;

use Psr\Http\Message\RequestInterface;

/**
 * @api
 */
interface RequestExceptionInterface extends CurlerExceptionInterface
{
    /**
     * Get the request associated with the exception
     */
    public function getRequest(): RequestInterface;
}
