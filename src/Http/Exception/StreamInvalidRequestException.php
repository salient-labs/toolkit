<?php declare(strict_types=1);

namespace Lkrms\Http\Exception;

/**
 * Thrown when a stream wrapper receives a request the underlying PHP stream
 * cannot service
 */
class StreamInvalidRequestException extends \Lkrms\Exception\Exception {}
