<?php declare(strict_types=1);

namespace Lkrms\Exception;

/**
 * Thrown when one or more .env files contain invalid syntax
 *
 */
class InvalidDotenvSyntaxException extends \Lkrms\Exception\MultipleErrorException {}
