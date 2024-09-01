<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Client\RequestExceptionInterface;

/**
 * @internal
 */
class RequestException extends AbstractRequestException implements RequestExceptionInterface {}
