<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Client\RequestExceptionInterface as PsrRequestExceptionInterface;
use Salient\Contract\Curler\Exception\RequestException as RequestExceptionInterface;

/**
 * @internal
 */
class RequestException extends GenericRequestException implements
    RequestExceptionInterface,
    PsrRequestExceptionInterface {}
