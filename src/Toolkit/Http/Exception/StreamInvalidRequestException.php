<?php declare(strict_types=1);

namespace Salient\Http\Exception;

use Salient\Core\AbstractException;

/**
 * Thrown when a stream wrapper receives a request the underlying PHP stream
 * cannot service
 */
class StreamInvalidRequestException extends AbstractException {}
