<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Salient\Contract\Curler\Exception\TooManyRedirectsException as TooManyRedirectsExceptionInterface;

/**
 * @internal
 */
class TooManyRedirectsException extends GenericResponseException implements TooManyRedirectsExceptionInterface {}
