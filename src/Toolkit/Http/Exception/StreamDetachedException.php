<?php declare(strict_types=1);

namespace Salient\Http\Exception;

use Salient\Core\AbstractException;

/**
 * Thrown when a stream wrapper receives a request it cannot service because it
 * has been detached from the underlying PHP stream
 */
class StreamDetachedException extends AbstractException {}
