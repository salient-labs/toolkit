<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Client\RequestExceptionInterface;

class RequestException extends AbstractRequestException implements RequestExceptionInterface {}
