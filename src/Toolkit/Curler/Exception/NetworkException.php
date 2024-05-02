<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Client\NetworkExceptionInterface;

class NetworkException extends AbstractRequestException implements NetworkExceptionInterface {}
