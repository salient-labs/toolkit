<?php declare(strict_types=1);

namespace Lkrms\Console\Exception;

use Lkrms\Exception\Exception;

/**
 * Thrown when a console output target receives a message after closing its
 * underlying resources
 */
class ConsoleInvalidTargetException extends Exception {}
