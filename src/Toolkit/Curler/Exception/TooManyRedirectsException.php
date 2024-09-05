<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Salient\Contract\Curler\Exception\TooManyRedirectsExceptionInterface;

/**
 * @internal
 */
class TooManyRedirectsException extends AbstractResponseException implements TooManyRedirectsExceptionInterface {}
