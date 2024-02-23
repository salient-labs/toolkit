<?php declare(strict_types=1);

namespace Salient\Cli\Exception;

use Salient\Core\AbstractException;

/**
 * Thrown when an unknown value is rejected by a command line option
 *
 * @api
 */
class CliUnknownValueException extends AbstractException {}
