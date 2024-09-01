<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Client\NetworkExceptionInterface;

/**
 * @internal
 */
class NetworkException extends AbstractRequestException implements NetworkExceptionInterface {}
