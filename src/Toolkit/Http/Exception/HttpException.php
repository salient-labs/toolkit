<?php declare(strict_types=1);

namespace Salient\Http\Exception;

use Salient\Contract\Http\Exception\HttpException as HttpExceptionInterface;
use Salient\Core\Exception\Exception;

/**
 * Base class for HTTP exceptions
 */
class HttpException extends Exception implements HttpExceptionInterface {}
