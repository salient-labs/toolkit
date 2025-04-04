<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Exception;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;

/**
 * @api
 */
interface RequestException extends CurlerException
{
    /**
     * Get the request associated with the exception
     */
    public function getRequest(): PsrRequestInterface;
}
