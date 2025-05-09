<?php declare(strict_types=1);

namespace Salient\Http\Exception;

use Salient\Contract\Http\Exception\HttpException as HttpExceptionInterface;
use Salient\Core\Exception\Exception;

/**
 * @internal
 */
class HttpException extends Exception implements HttpExceptionInterface {}
