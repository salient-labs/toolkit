<?php declare(strict_types=1);

namespace Salient\Cli\Exception;

use Salient\Core\Exception\Exception;

/**
 * Thrown when an unknown value is rejected by a command line option
 */
class CliUnknownValueException extends Exception {}
