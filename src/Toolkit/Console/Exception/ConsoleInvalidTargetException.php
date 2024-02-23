<?php declare(strict_types=1);

namespace Salient\Console\Exception;

use Salient\Core\AbstractException;

/**
 * Thrown when a console output target receives a message after closing its
 * underlying resources
 */
class ConsoleInvalidTargetException extends AbstractException {}
