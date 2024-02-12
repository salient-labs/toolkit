<?php declare(strict_types=1);

namespace Lkrms\Cli\Exception;

use Lkrms\Exception\Exception;

/**
 * Thrown when an unknown value is rejected by a command line option
 *
 * @api
 */
class CliUnknownValueException extends Exception {}
