<?php declare(strict_types=1);

namespace Salient\Http\Exception;

use Salient\Contract\Http\Exception\InvalidHeaderException as InvalidHeaderExceptionInterface;

/**
 * @internal
 */
class InvalidHeaderException extends HttpException implements InvalidHeaderExceptionInterface {}
