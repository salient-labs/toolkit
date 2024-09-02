<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Client\NetworkExceptionInterface as PsrNetworkExceptionInterface;
use Salient\Contract\Curler\Exception\RequestExceptionInterface;

/**
 * @internal
 */
class NetworkException extends AbstractRequestException implements
    RequestExceptionInterface,
    PsrNetworkExceptionInterface {}
