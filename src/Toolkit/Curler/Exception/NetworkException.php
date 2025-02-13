<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Client\NetworkExceptionInterface as PsrNetworkExceptionInterface;
use Salient\Contract\Curler\Exception\RequestException as RequestExceptionInterface;

/**
 * @internal
 */
class NetworkException extends GenericRequestException implements
    RequestExceptionInterface,
    PsrNetworkExceptionInterface {}
