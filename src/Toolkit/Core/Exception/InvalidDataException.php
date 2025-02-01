<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Core\Exception\InvalidDataExceptionInterface;
use LogicException;

/**
 * @api
 */
class InvalidDataException extends LogicException implements InvalidDataExceptionInterface {}
